<?php
namespace QF\DB;

class Repository
{
    /**
     *
     * @var \PDO
     */
    protected $db = null;
    
    protected $entityClass = null;
    
    protected static $defaultEntityClass = null;
    
    public function __construct($db, $entityClass = null)
    {
        $this->db = $db;

        if ($entityClass) { 
            if (is_object($entityClass)) {
                $entityClass = get_class($entityClass);
            }
            
            $this->entityClass = $entityClass;
        }
        if (!is_subclass_of($this->getEntityClass(), '\\QF\\DB\\Entity')) {
            throw new \InvalidArgumentException('$entityClass must be an \\QF\\DB\\Entity instance or classname');
        }
    }
    
    public function getEntityClass()
    {
        return $this->entityClass ?: static::$defaultEntityClass;
    }
    
    /**
     *
     * @return \PDO
     */
    public function getDB()
    {
        return $this->db;
    }
    
    public function setDB($db)
    {
        $this->db = $db;
    }
    
    /**
     *
     * @param array $data initial data for the model
     * @param bool $isNew whether this is a new object (true, default) or loaded from database (false)
     * @param string $entityClass the class to create or null for the current Repositories entity class
     * @return Entity
     */
    public function create($data = array(), $isNew = true, $entityClass = null)
    {
        if ($entityClass === null) {
            $entityClass = $this->getEntityClass();
        }
        $entity = new $entityClass($this->getDB());
        if ($isNew) {
            foreach ($data as $k => $v) {
                $entity->set($k, $v);
            }
        } else {
            foreach ($data as $k => $v) {
                $entity->set($k, $v);
                $entity->setDatabaseProperty($k, $v);
            }
            $entity->postLoad($this->getDB());
        }
        return $entity;
    }
    
    public function save(Entity $entity)
    {
        $entityClass = get_class($entity);
        try
        {
            if ($entity->preSave($this->getDB(), !$this->isNew()) === false) {
                return false;
            }
            if ($entity->isNew()) {

                $fields = array();
                $values = array();
                foreach ($entityClass::getColumns() as $column)
                {
                    if ($entity->get($column) !== null)
                    {
                        $fields[] = $column;
                        //$query->set(' '.$column.' = ? ');
                        $values[] = $entity->get($column);
                    }
                }

                $query = $this->getDB()->prepare('INSERT INTO '.$entityClass::getTablename().' ('.implode(',',$fields).') VALUES ('.implode(',', array_fill(0, count($fields), '?')).')');

                $result = $query->execute($values);

                if ($entityClass::isAutoIncrement())
                {
                    $entity->set($entityClass::getIdentifier(), $this->getDB()->lastInsertId());
                }

                foreach ($entityClass::getColumns() as $column)
                {
                    $entity->setDatabaseProperty($column, $entity->get($column));
                } 
                
                if ($result) {
                    $entity->postSave($this->getDB(), false);
                }
                
                return (bool) $result;

            } else {
                $update = false;
                
                $fields = array();
                $values = array();
                foreach ($entityClass::getColumns() as $column)
                {
                    if ($entity->get($column) !== $entity->getDatabaseProperty($column))
                    {
                        $fields[] = $column;
                        //$query->set(' '.$column.' = ? ');
                        $values[] = $entity->get($column);
                        $update = true;
                    }
                }

                $query = $this->getDB()->prepare('UPDATE '.$entityClass::getTableName().' SET '.implode('=?, ',$fields).'=? WHERE '.$entityClass::getIdentifier().' = ?');

                if (!$update) {
                    return true;
                }

                $values[] = $entity->get($entityClass::getIdentifier());

                $result = $query->execute($values);

                foreach ($entityClass::getColumns() as $column)
                {
                    if ($entity->has($column) && $entity->get($column) !== $entity->getDatabaseProperty($column))
                    {
                        $entity->setDatabaseProperty($column, $entity->get($column));
                    }
                }
                
                if ($result) {
                    $entity->postSave($this->getDB(), true);
                }
                
                return (bool) $result;
            }
            
        } catch (Exception $e) {
            throw $e;
        }

	}
    
    /**
     * 
     * @param array|string $set array of column => value pairs to update or (only for raw=true) update expression(s) as string or numeric array (e.g. foo = foo + 2)
     * @param array|string $conditions the where conditions
     * @param array $values values for ?-placeholders in the conditions
     * @param bool $raw do a raw (direct) update in the db or load each entity first and perform a separate update/save
     * @param bool $cleanRefTable remove unused entries in m:n ref tables
     * @return boolean
     */
    public function updateBy($set, $conditions, $values = array(), $raw = false, $cleanRefTable = false)
	{
        if ($raw) {
            $entityClass = $this->getEntityClass();
            $query = 'UPDATE '.$entityClass::getTableName();

            $set = array();
            foreach ((array) $set as $k => $v) {
                if (is_numeric($k)) {
                    $set[] = ' '.$v;
                } else {
                    $set[] = ' '.$k.'='.$this->getDB()->quote($v);
                }
            }
            if ($set) {
                $query .= ' SET'.implode(' , ', $set);
            }
            
            $where = array();
            foreach ((array) $conditions as $k => $v) {
                if (is_numeric($k)) {
                    $where[] = ' '.$v;
                } else {
                    $where[] = ' '.$k.'='.$this->getDB()->quote($v);
                }
            }
            if ($where) {
                $query .= ' WHERE'.implode(' AND ', $where);
            }
            $stmt = $this->getDB()->prepare($query);
            $result = $stmt->execute($values);

            if ($cleanRefTable) {
                $this->cleanRefTables();
            }

            return $result;
        } else {
            foreach($this->load($conditions, $values) as $entity) {
                foreach ($set as $k => $v) {
                    $entity->set($k, $v);
                }
                $entity->save();
            } 
            return true;
        }
	}
    
    public function remove($entity)
    {
        try
        {
            if (is_object($entity))
            {
                if ($entity->preRemove($this->getDB()) === false) {
                    return false;
                }
            
                $entityClass = get_class($entity);
                if (!$entity->has($entityClass::getIdentifier()))
                {
                    return false;
                }

                $query = $this->getDB()->prepare('DELETE FROM '.$entityClass::getTableName().' WHERE '.$entityClass::getIdentifier().' = ? LIMIT 1');
                $result = $query->execute(array($entity->get($entityClass::getIdentifier())));
                $entity->clearDatabaseProperties();
                
                if ($result) {
                    $entity->postRemove($this->getDB());
                }
            }
            else
            {
                $entityClass = $this->entityClass;
                $query = $this->getDB()->prepare('DELETE FROM '.$entityClass::getTableName().' WHERE '.$entityClass::getIdentifier().' = ? LIMIT 1');
                $result = $query->execute($entity);
            }
            foreach ($entityClass::getRelations() as $relation => $info) {
                if (isset($info[3]) && $info[3] !== true) {
                    $query = $this->getDB()->prepare('DELETE FROM '.$info[3].' WHERE '.$info[1].' = ?');
                    $query->execute(array(is_object($entity) ? $entity->get($entityClass::getIdentifier()) : $entity));
                }
            }

        } catch (Exception $e) {
            throw $e;
        }
		return $result;
	}
    
    /**
     * 
     * @param array|string $conditions the where conditions
     * @param array $values values for ?-placeholders in the conditions
     * @param bool $raw do a raw (direct) update in the db or load each entity first and perform a separate 
     * @param bool $cleanRefTable remove unused entries in m:n ref tables
     * @return boolean
     */
    public function removeBy($conditions, $values = array(), $raw = false, $cleanRefTable = false)
	{
        if ($raw) {
            $entityClass = $this->getEntityClass();
            $query = 'DELETE FROM '.$entityClass::getTableName();

            $where = array();
            foreach ((array) $conditions as $k => $v) {
                if (is_numeric($k)) {
                    $where[] = ' '.$v;
                } else {
                    $where[] = ' '.$k.'='.$this->getDB()->quote($v);
                }
            }
            if ($where) {
                $query .= ' WHERE'.implode(' AND ', $where);
            }
            $stmt = $this->getDB()->prepare($query);
            $result = $stmt->execute($values);

            if ($cleanRefTable) {
                $this->cleanRefTables();
            }

            return $result;
        } else {
            foreach($this->load($conditions, $values) as $entity) {
                $entity->delete();
            } 
            return true;
        }
	}
    
    /**
     * deletes all rows in m:n ref tables which have no related entry in this class
     */
    public function cleanRefTables()
    {
        $entityClass = $this->getEntityClass();
        foreach ($entityClass::getRelations() as $relation => $info) {
            if (!isset($info[3]) || $info[3] === true) {
                continue;
            }
            $stmt = $this->getDB()->prepare('SELECT a_b.'.$info[1].' rel1, a_b.'.$info[2].' rel2 FROM '.$info[3].' a_b LEFT JOIN '.$entityClass::getTableName().' a ON a_b.'.$info[1].' = a.'.$entityClass::getIdentifier().' WHERE a.'.$entityClass::getIdentifier().' IS NULL')->execute();
            $refTableIds = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $where = array();
            $values = array();
            foreach ($refTableIds as $row) {
                $where[] = '('.$info[1].' = ? AND '.$info[2].' = ?)';
                $values[] = $row['rel1'];
                $values[] = $row['rel2'];
            }
            $deleteStmt = $this->getDB()->prepare('DELETE FROM '.$info[3].' WHERE '.implode(' OR ', $where))->execute($values);
        }
    }
    
    /**
     *
     * @param array|string $conditions the where conditions
     * @param array $values values for ?-placeholders in the conditions
     * @param string $order an order by clause (id ASC, foo DESC)
     * @return Entity
     */
    public function loadOne($conditions = array(), $values = array(), $order = null)
    {
        $entities = $this->load($conditions, $values, $order, 1, 0, true);
        return reset($entities);
    }
    
    /**
     *
     * @param array|string $conditions the where conditions
     * @param array $values values for ?-placeholders in the conditions
     * @param string $order an order by clause (id ASC, foo DESC)
     * @param int $limit
     * @param int $offset
     * @param bool $build true to build the entities, false to return the statement
     * @return array|\PDOStatement
     */
    public function load($conditions = array(), $values = array(), $order = null, $limit = null, $offset = null, $build = true)
    {
        $entity = $this->getEntityClass();

        if (!is_subclass_of($entity, '\\QF\\DB\\Entity')) {
            throw new \InvalidArgumentException('$entity must be an \\QF\\DB\\Entity instance or classname');
        }
        $query = 'SELECT '.implode(', ', $entity::getColumns()).' FROM '.$entity::getTableName();
        $where = array();
        foreach ((array) $conditions as $k => $v) {
            if (is_numeric($k)) {
                $where[] = ' '.$v;
            } else {
                $where[] = ' '.$k.'='.$this->getDB()->quote($v);
            }
        }
        if ($where) {
            $query .= ' WHERE'.implode(' AND ', $where);
        }
        if ($order) {
            $query .= ' ORDER BY '.$order;
        }
        if ($limit || $offset) {
            $query .= ' LIMIT '.(int)$limit.((int)$offset ? ' OFFSET '.(int)$offset : '');
        }
        $stmt = $this->getDB()->prepare($query);
        $stmt->execute(array_values((array) $values));
        
        if ($build) {
            return $this->build($stmt, $entity);
        } else {
            return $stmt;
        }     
    }
    
    /**
     * $relations is an array of arrays as followed:
     *  array(fromAlias, relationProperty, toAlias, options = array())
     *      options is an array with the following optional keys:
     *          'conditions' => array|string additional where conditions to filter for
     *          'values' => array values for ?-placeholders in the conditions
     *          'order' => string the order of the related entries
     *          'aggregate' => false|array fetch only an aggregated value, not the entries themself
     *                         if not false, must be an array('aggregationFunction' => 'targetProperty')
     * 
     *      the fromAlias of the initial entity is 'a'
     * 
     *      if aggregate is set, the result of the aggregate function (array-key) will be saved in the property of the from-object (array-value)
     *      (example: array('count(*)' => 'fooCount', 'sum(score)' => 'fooScore') will save the number of related entries in $fromObject->fooCount and the sum $fromObject->fooSum)
     *      when using aggregate on m:n relations, you should prefix columns in conditions and aggregate functions with 'b' ('conditions' => 'b.status = 1', 'aggregate' => array('sum(b.score)' => 'fooScore'))
     * 
     * @param array $relations the relations
     * @param array|string $conditions the where conditions
     * @param array $values values for ?-placeholders in the conditions
     * @param string $order an order by clause (id ASC, foo DESC)
     * @return mixed the first entity found or false
     */
    public function loadOneWithRelations($relations = array(), $conditions = array(), $values = array(), $order = null)
    {
        $results = $this->loadWithRelations($relations, $conditions, $values, $order, 1);
        return reset($results);
    }
    
    /**
     * $relations is an array of arrays as followed:
     *  array(fromAlias, relationProperty, toAlias, options = array())
     *      options is an array with the following optional keys:
     *          'conditions' => array|string additional where conditions to filter for
     *          'values' => array values for ?-placeholders in the conditions
     *          'order' => string the order of the related entries
     *          'aggregate' => false|array fetch only an aggregated value, not the entries themself
     *                         if not false, must be an array('aggregationFunction' => 'targetProperty')
     * 
     *      the fromAlias of the initial entity is 'a'
     * 
     *      if aggregate is set, the result of the aggregate function (array-key) will be saved in the property of the from-object (array-value)
     *      (example: array('count(*)' => 'fooCount', 'sum(score)' => 'fooScore') will save the number of related entries in $fromObject->fooCount and the sum $fromObject->fooSum)
     *      when using aggregate on m:n relations, you should prefix columns in conditions and aggregate functions with 'b' ('conditions' => 'b.status = 1', 'aggregate' => array('sum(b.score)' => 'fooScore'))
     * 
     * @param array $relations the relations
     * @param array|string $conditions the where conditions
     * @param array $values values for ?-placeholders in the conditions
     * @param string $order an order by clause (id ASC, foo DESC)
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function loadWithRelations($relations = array(), $conditions = array(), $values = array(), $order = null, $limit = null, $offset = null)
    {
        $entityClasses = array();
        $entityClasses['a'] = $this->getEntityClass();

        if (!is_subclass_of($entityClasses['a'], '\\QF\\DB\\Entity')) {
            throw new \InvalidArgumentException('$entity must be an \\QF\\DB\\Entity instance or classname');
        }
        
        $query = 'SELECT '.implode(', ', $entityClasses['a']::getColumns()).' FROM '.$entityClasses['a']::getTableName();
        $where = array();
        foreach ((array) $conditions as $k => $v) {
            if (is_numeric($k)) {
                $where[] = ' '.$v;
            } else {
                $where[] = ' '.$k.'='.$this->getDB()->quote($v);
            }
        }
        if ($where) {
            $query .= ' WHERE'.implode(' AND ', $where);
        }
        if ($order) {
            $query .= ' ORDER BY '.$order;
        }
        if ($limit || $offset) {
            $query .= ' LIMIT '.(int)$limit.((int)$offset ? ' OFFSET '.(int)$offset : '');
        }
        $stmt = $this->getDB()->prepare($query);
        $stmt->execute(array_values((array) $values));
        
        $entities = array();
        $entities['a'] = $this->build($stmt, $entityClasses['a']);
        
        foreach ($relations as $rel) {
            if (empty($rel[0]) || !isset($entityClasses[$rel[0]])) {
                throw new \Exception('unknown fromAlias '.$rel[0]);
            }
            if (empty($rel[2])) {
                throw new \Exception('missing toAlias');
            }
            
            if (!is_subclass_of($entityClasses[$rel[0]], '\\QF\\DB\\Entity')) {
                throw new \InvalidArgumentException('$entity must be an \\QF\\DB\\Entity instance or classname');
            }
            
            if (empty($rel[1]) || !$relData = $entityClasses[$rel[0]]::getRelation($rel[1])) {
                throw new \Exception('unknown relation '.$rel[0].'.'.$rel[1]);
            }
            
            $entityClasses[$rel[2]] = $relData[0];
            
            $options = !empty($rel[3]) ? (array) $rel[3] : array();
            $values = (array) (!empty($rel[4]) ? $rel[4] : null);
            $condition = (array) (!empty($rel[3]) ? $rel[3] : null);
                
            $repository = $relData[0]::getRepository($this->getDB());
            
            if (isset($relData[3]) && $relData[3] !== true) {
                $refValues = array();
                foreach ($entities[$rel[0]] as $fromEntity) {
                    array_push($refValues, $fromEntity->get($entityClasses[$rel[0]]::getIdentifier()));
                }
                    
                if (!empty($options['aggregate'])) {
                    foreach ($options['aggregate'] as $aggregateFunction => $aggregateTargetProperty) {
                        $query = 'SELECT a.'.$relData[1].' identifier, '.$aggregateFunction.' aggregateValue FROM '.$relData[3].' a JOIN '.$relData[0]::getTableName().' b ON a.'.$relData[2].'=b.'.$relData[0]::getIdentifier().' WHERE a.'.$relData[1].' IN ('.implode(',', array_fill(0, count($entities[$rel[0]]), '?')).')';
                        
                        $where = array();
                        foreach ((array) $conditions as $k => $v) {
                            if (is_numeric($k)) {
                                $where[] = ' '.$v;
                            } else {
                                $where[] = ' '.$k.'='.$this->getDB()->quote($v);
                            }
                        }
                        if ($where) {
                            $query .= ' '.implode(' AND ', $where);
                        }
                        $query .= ' GROUP BY a.'.$relData[1];
                        $stmt = $this->getDB()->prepare($query)->execute(array_merge($refValues, $values));
                        $aggregateResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($entities[$rel[0]] as $fromEntity) {
                            foreach ($aggregateResults as $aggregateResult) {
                                if ($fromEntity->get($entityClasses[$rel[0]]::getIdentifier()) == $aggregateResults['identifier']) {
                                    $fromEntity->set($aggregateTargetProperty, $aggregateResults['aggregateValue']);
                                }
                            }
                        }
                    }
                } else {
                    $stmt = $this->getDB()->prepare('SELECT '.$relData[1].' a, '.$relData[2].' b FROM '.$relData[3].' WHERE '.$relData[1].' IN ('.implode(',', array_fill(0, count($entities[$rel[0]]), '?')).')')
                            ->execute($refValues);
                    $refTableIds = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    foreach ($refTableIds as $row) {
                        array_push($values, $row['b']);
                    }

                    array_push($condition, $relData[0]::getIdentifier().' IN ('.implode(',', array_fill(0, count($refTableIds), '?')).')');

                    $entities[$rel[2]] = $repository->load($condition, $values, !empty($options['order']) ? $options['order'] : null);

                    foreach ($refTableIds as $row) {
                        $entities[$rel[0]][$row['a']]->add($rel[1], $entities[$rel[2]][$row['b']]);
                    }
                }
                
            } else {                
                foreach ($entities[$rel[0]] as $fromEntity) {
                    array_push($values, $fromEntity->get($relData[1]));
                }
                
                array_push($condition, $relData[2].' IN ('.implode(',', array_fill(0, count($entities[$rel[0]]), '?')).')');

                if (!empty($options['aggregate'])) {
                    foreach ($options['aggregate'] as $aggregateFunction => $aggregateTargetProperty) {
                        $query = 'SELECT '.$relData[2].' identifier, '.$aggregateFunction.' aggregateValue FROM '.$relData[0]::getTableName().' WHERE ';
                        $where = array();
                        foreach ((array) $conditions as $k => $v) {
                            if (is_numeric($k)) {
                                $where[] = ' '.$v;
                            } else {
                                $where[] = ' '.$k.'='.$this->getDB()->quote($v);
                            }
                        }
                        if ($where) {
                            $query .= ' WHERE'.implode(' AND ', $where);
                        }
                        $query .= ' GROUP BY '.$relData[2];
                        $stmt = $this->getDB()->prepare($query)->execute($values);
                        $aggregateResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($entities[$rel[0]] as $fromEntity) {
                            foreach ($aggregateResults as $aggregateResult) {
                                if ($fromEntity->get($relData[1]) == $aggregateResults['identifier']) {
                                    $fromEntity->set($aggregateTargetProperty, $aggregateResults['aggregateValue']);
                                }
                            }
                        }
                    }
                } else {
                    $entities[$rel[2]] = $repository->load($condition, $values, !empty($options['order']) ? $options['order'] : null);

                    $entityTmp = array();
                    
                    foreach ($entities[$rel[0]] as $fk => $fromEntity) {
                        foreach ($entities[$rel[2]] as $fk => $toEntity) {
                            if (!isset($entityTmp[0][$fk.'_'.$relData[1]])) {
                                $entityTmp[0][$fk.'_'.$relData[1]] = $fromEntity->get($relData[1]);
                            }
                            if (!isset($entityTmp[1][$tk.'_'.$relData[2]])) {
                                $entityTmp[1][$tk.'_'.$relData[2]] = $toEntity->get($relData[2]);
                            }
                            if ($entityTmp[0][$fk.'_'.$relData[1]] == $entityTmp[1][$tk.'_'.$relData[2]]) {
                                if (!empty($relData[3])) {
                                    $fromEntity->set($rel[1], $toEntity);
                                } else {
                                    $fromEntity->add($rel[1], $toEntity);
                                }
                            }
                        }
                    }
                }
            }
        }
        
        return $entities['a'];
        
    }
    
    /**
     *
     * @param array|string $conditions the where conditions
     * @param array $values values for ?-placeholders in the conditions
     * @return int
     */
    public function count($conditions = array(), $values = array())
    {
        $entity = $this->getEntityClass();

        if (!is_subclass_of($entity, '\\QF\\DB\\Entity')) {
            throw new \InvalidArgumentException('$entity must be an \\QF\\DB\\Entity instance or classname');
        }
        $query = 'SELECT count('.$entity::getIdentifier().') FROM '.$entity::getTableName();
        $where = array();
        foreach ((array) $conditions as $k => $v) {
            if (is_numeric($k)) {
                $where[] = ' '.$v;
            } else {
                $where[] = ' '.$k.'='.$this->getDB()->quote($v);
            }
        }
        if ($where) {
            $query .= ' WHERE'.implode(' AND ', $where);
        }
        
        $stmt = $this->getDB()->prepare($query);
        $stmt->execute(array_values((array) $values));
        
        $count = $stmt->fetchColumn();
        $stmt->closeCursor();
           
        return $count;
    }
    
    
    
    /**
     * builds entities from a PDOStatement
     * 
     * @param \PDOStatement $statement the pdo statement
     * @param mixed $entityClass the entity class to use
     * @return array the resulting entities 
     */
    public function build(\PDOStatement $statement, $entityClass = null)
    {
        if ($entityClass === null) {
            $entityClass = $this->getEntityClass();
        }

        $results = $statement->fetchAll(\PDO::FETCH_ASSOC);
        $returnData = array();
        $key = $entityClass::getIdentifier();
        foreach ($results as $row) {
            $entity = $this->create($row, false, $entityClass);
            $returnData[$entity->$key] = $entity;
        }
        
        return $returnData; 
    }
   
    /**
     * alternative method to load entities with relations (this method uses one Query with JOINS)
     *      differences to loadWithRelations:
     *      - performance may vary based on number of fetched relations
     *      - cross-table conditions (e.g. a.foo = b.bar)
     *      - option to select certain referenced tables
     *      - no ORDER BY for related entities, but one global order by clause
     *      - the prefix/alias must be used in any conditions
     *      - group by support
     * 
     * $relations is an array of arrays as followed:
     *  array(fromAlias, relationProperty, toAlias, options = array())
     *      options is an array with the following optional keys:
     *          'select' => true|false|string true to select/fetch the complete entity, false to not select the entity, string for custom select
     *          'conditions' => array|string additional where conditions to filter for
     *          'values' => array values for ?-placeholders in the conditions
     *          'on' => string additional conditions for on clause (this is appended after the default ON condition) example: toAlias.status = "published"
     * 
     *      the fromAlias of the initial entity is 'a'
     * 
     *      if select is a string, any fetched value must be aliased with "ALIAS_PROPERTY" (b.some_column as b_status will be added as "status"-property to the entity with alias b)
     *      you have to select at least the identifier of an related entity (toAlias.id as toAlias_id), otherwise the entity is not created
     *      to fetch aggregated values for the relation, use something like "COUNT(toAlias.id) as fromAlias_relation_count" to add the number of related entities as the relation_count property (and don´t forget to use GroupBy)
     * 
     * @param array $relations the relations
     * @param array|string $conditions the where conditions
     * @param array $values values for ?-placeholders in the conditions
     * @param string $order an order by clause (a.id ASC, b.foo DESC)
     * @param string $groupBy an group by clause
     * @return mixed the first entity found or false
     */
    public function loadOneWithRelationsAlt($relations = array(), $conditions = array(), $values = array(), $order = null, $groupBy = null)
    {
        $results = $this->loadWithRelationsAlt($relations, $conditions, $values, $order, 1, null, $groupBy);
        return reset($results);
    }
    
    /**
     * alternative method to load entities with relations (this method uses one Query with JOINS)
     *      differences to loadWithRelations:
     *      - performance may vary based on number of fetched relations
     *      - cross-table conditions (e.g. a.foo = b.bar)
     *      - option to select certain referenced tables
     *      - no ORDER BY for related entities, but one global order by clause
     *      - the prefix/alias must be used in any conditions
     *      - group by support
     * 
     * $relations is an array of arrays as followed:
     *  array(fromAlias, relationProperty, toAlias, options = array())
     *      options is an array with the following optional keys:
     *          'select' => true|false|string true to select/fetch the complete entity, false to not select the entity, string for custom select
     *          'conditions' => array|string additional where conditions to filter for
     *          'values' => array values for ?-placeholders in the conditions
     *          'on' => string additional conditions for on clause (this is appended after the default ON condition) example: toAlias.status = "published"
     * 
     *      the fromAlias of the initial entity is 'a'
     *      if select is a string, any fetched value must be aliased with "ALIAS_PROPERTY" (b.some_column as b_status will be added as "status"-property to the entity with alias b)
     *      you have to select at least the identifier of an related entity (toAlias.id as toAlias_id), otherwise the entity is not created
     *      to fetch aggregated values for the relation, use something like "COUNT(toAlias.id) as fromAlias_relation_count" to add the number of related entities as the relation_count property (and don´t forget to use GroupBy)
     * 
     * @param array $relations the relations
     * @param array|string $conditions the where conditions
     * @param array $values values for ?-placeholders in the conditions
     * @param string $order an order by clause (a.id ASC, b.foo DESC)
     * @param int $limit
     * @param int $offset
     * @param string $groupBy an group by clause
     * @return array the resulting entities 
     */
    public function loadWithRelationsAlt($relations = array(), $conditions = array(), $values = array(), $order = null, $limit = null, $offset = null, $groupBy = null)
    {
        $entityClasses = array();
        $entityIdentifiers = array();
        $entityRepositories = array();
        $entityClasses['a'] = $this->getEntityClass();
        
        if (!is_subclass_of($entityClasses['a'], '\\QF\\DB\\Entity')) {
            throw new \InvalidArgumentException('$entity must be an \\QF\\DB\\Entity instance or classname');
        }
        
        $entityIdentifiers['a'] = $entityClasses['a']::getIdentifier();
        $entityRepositories['a'] = $entityClasses['a']::getRepository($this->getDB());
        
        $placeholders = array();
        $query = 'SELECT '.implode(', ', $entityClasses['a']::getColumns('a'));
        
        $needPreQuery = false;
        
        foreach ($relations as $k => $rel) {
                if (empty($rel[0]) || !isset($entityClasses[$rel[0]])) {
                    throw new \Exception('unknown fromAlias '.$rel[0]);
                }
                if (empty($rel[2])) {
                    throw new \Exception('missing toAlias');
                }

                if (!is_subclass_of($entityClasses[$rel[0]], '\\QF\\DB\\Entity')) {
                    throw new \InvalidArgumentException('$entity must be an \\QF\\DB\\Entity instance or classname');
                }

                if (empty($rel[1]) || !$relData = $entityClasses[$rel[0]]::getRelation($rel[1])) {
                    throw new \Exception('unknown relation '.$rel[0].'.'.$rel[1]);
                }

                $entityClasses[$rel[2]] = $relData[0];
                $entityIdentifiers[$rel[2]] = $entityClasses[$rel[2]]::getIdentifier();
                $entityRepositories[$rel[2]] = $entityClasses[$rel[2]]::getRepository($this->getDB());
                
                $relations[$k][4] = $entityClasses[$rel[2]]::getRelation($rel[1]);
                
                if (!empty($rel[3]['select'])) {
                    $query .= ', '.($rel[3]['select'] === true ? implode(', ', $entityClasses[$rel[2]]::getColumns($rel[2])) : $rel[3]['select']);
                }
        }
        
        $query .= ' FROM '.$entityClasses['a']::getTableName().' a ';
        
        $relQuery = array();
        foreach ($relations as $rel) {
            $currentRelQuery = '';
            if (!empty($rel[4][3]) && $rel[4][3] !== true) {
                $needPreQuery = true;
                $currentRelQuery .= ' LEFT JOIN '.$rel[4][3].' '.$rel[0].'_'.$rel[2].' ON '.$rel[0].'.'.$entityClasses[$rel[0]]::getIdentifier().' = '.$rel[0].'_'.$rel[2].'.'.$rel[4][1].' LEFT JOIN '.$entityClasses[$rel[2]]::getTableName().' '.$rel[2].' ON '.$rel[0].'_'.$rel[2].'.'.$rel[4][2].' = '.$entityClasses[$rel[2]]::getIdentifier();
                if (!empty($rel[3]['on'])) {
                    $currentRelQuery .= ' AND '.$rel[3]['on'];
                }
            } else {
                if (empty($rel[4][3])) {
                    $needPreQuery = true;
                }
                $currentRelQuery .= ' LEFT JOIN '.$entityClasses[$rel[2]]::getTableName().' '.$rel[2].' ON '.$rel[0].'.'.$rel[4][1].' = '.$rel[2].'.'.$rel[4][2];
                if (!empty($rel[3]['on'])) {
                    $currentRelQuery .= ' AND '.$rel[3]['on'];
                }
            }
            if (!empty($rel[3]['conditions'])) {
                $where = array();
                foreach ((array) $rel[3]['conditions'] as $k => $v) {
                    if (is_numeric($k)) {
                        $where[] = ' '.$v;
                    } else {
                        $where[] = ' '.$k.'='.$this->getDB()->quote($v);
                    }
                }
                if ($where) {
                    $currentRelQuery .= ' AND'.implode(' AND ', $where);
                }
            }
            if (!empty($rel[3]['values'])) {
                $placeholders = array_merge($placeholders, (array) $rel[3]['values']);
            }
            $relQuery[] = $currentRelQuery;
        }
        
        $query .= implode(' ', $relQuery);
        
        $where = array();
        foreach ((array) $conditions as $k => $v) {
            if (is_numeric($k)) {
                $where[] = ' '.$v;
            } else {
                $where[] = ' '.$k.'='.$this->getDB()->quote($v);
            }
        }
        

        if ($needPreQuery && empty($groupBy) && ($limit || $offset)) {
            $preQuery = 'SELECT a.'.$entityClasses['a']::getIdentifier().' FROM'.$entityClasses['a']::getTableName().' a ';
            
            if ($where) {
                $preQuery .= ' WHERE'.implode(' AND ', $where);
            }
            if ($limit || $offset) {
                $preQuery .= ' LIMIT '.(int)$limit.((int)$offset ? ' OFFSET '.(int)$offset : '');
            }
            
            $preStmt = $this->getDB()->prepare($preQuery);
            $preStmt->execute(array_values((array) $values));
            $inIds = $preStmt->fetchAll(\PDO::FETCH_COLUMN);
            $placeholders = array_merge($placeholders, (array) $inIds);
            array_unshift($where, 'a.'.$entityClasses['a']::getIdentifier().' IN ('.implode(',', array_fill(0, count($inIds), '?')).') ');
        }
        
        if ($where) {
            $query .= ' WHERE'.implode(' AND ', $where);
        }
        $placeholders = array_merge($placeholders, (array) $values);  
        
        
        if ($order) {
            $query .= ' ORDER BY '.$order.' ';
        }
        
        if ($groupBy) {
            $query .= ' GROUP BY '.$groupBy.' ';
        }
        
        if (!$needPreQuery && ($limit || $offset)) {
            $query .= ' LIMIT '.(int)$limit.((int)$offset ? ' OFFSET '.(int)$offset : '');
        }
        
        
        
        $stmt = $this->getDB()->prepare($query);
        $stmt->execute(array_values((array) $placeholders));
        $results = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $returnData = array();
        $relationTemp = array();
        
        foreach ($results as $row) {
            foreach ($entityClasses as $prefix => $entityClass) {
                if (!empty($row[$prefix.'_'.$entityIdentifiers[$prefix]]) && empty($returnData[$prefix][$row[$prefix.'_'.$entityIdentifiers[$prefix]]])) {
                    $returnData[$prefix][$row[$prefix.'_'.$entityIdentifiers[$prefix]]] = $entityRepositories[$prefix]->create($this->filter($row, $prefix), false);
                }
            }

            foreach ($relations as $rel) {
                if (!empty($relationTemp[$rel[0].'_'.$row[$rel[0].'_'.$entityIdentifiers[$rel[0]]].'|'.$rel[2].'_'.$row[$rel[2].'_'.$entityIdentifiers[$rel[2]]]])) {
                    continue;
                }
                if (isset($rel[4][3]) && $rel[4][3] !== true) {
                    if (!empty($row[$rel[0].'_'.$entityIdentifiers[$rel[0]]]) && !empty($row[$rel[2].'_'.$entityIdentifiers[$rel[2]]]) && !empty($row[$rel[4][3].'_'.$rel[4][1]]) && !empty($row[$rel[4][3].'_'.$rel[4][2]]) && ($row[$rel[0].'_'.$entityIdentifiers[$rel[0]]] == $row[$rel[4][3].'_'.$rel[4][1]]) && ($row[$rel[2].'_'.$entityIdentifiers[$rel[2]]] == $row[$rel[4][3].'_'.$rel[4][2]])) {
                        $relationTemp[$rel[0].'_'.$row[$rel[0].'_'.$entityIdentifiers[$rel[0]]].'|'.$rel[2].'_'.$row[$rel[2].'_'.$entityIdentifiers[$rel[2]]]] = true;
                        $returnData[$rel[0]][$row[$rel[0].'_'.$entityIdentifiers[$rel[0]]]]->add($rel[1], $returnData[$rel[1]][$row[$rel[2].'_'.$entityIdentifiers[$rel[2]]]]);
                    }
                } else {   
                    if (!empty($row[$rel[0].'_'.$rel[4][1]]) && !empty($row[$rel[2].'_'.$rel[4][2]]) && ($row[$rel[0].'_'.$rel[4][1]] == $row[$rel[2].'_'.$rel[4][2]])) {
                        $relationTemp[$rel[0].'_'.$row[$rel[0].'_'.$entityIdentifiers[$rel[0]]].'|'.$rel[2].'_'.$row[$rel[2].'_'.$entityIdentifiers[$rel[2]]]] = true;
                        if (!empty($rel[3][3])) {
                            $returnData[$rel[0]][$row[$rel[0].'_'.$entityIdentifiers[$rel[0]]]]->set($rel[1], $returnData[$rel[1]][$row[$rel[2].'_'.$entityIdentifiers[$rel[2]]]]);
                        } else {
                            $returnData[$rel[0]][$row[$rel[0].'_'.$entityIdentifiers[$rel[0]]]]->add($rel[1], $returnData[$rel[1]][$row[$rel[2].'_'.$entityIdentifiers[$rel[2]]]]);
                        }
                    }
                }
            
            }
            
        }
        
        return $returnData['a'];
        
    }
    
    public static function filter($data, $prefix)
    {
        $return = array();
        foreach ($data as $key => $entry) {
            if (strpos($key, $prefix.'_') === 0) {
                $return[substr($key, strlen($prefix+1))] = $entry;
            }
        }
        return $return;
    }
    
    public static function get($db, $entityClass = null)
    {
        if (!$entityClass) { 
            $entityClass = static::$defaultEntityClass;
        } elseif (is_object($entityClass)) {
            $entityClass = get_class($entityClass);
        }
        return $entityClass::getRepository($db);
    }
}
