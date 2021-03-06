<?php
namespace QF\Mongo;

class Repository
{

    /**
     *
     * @var \MongoDB
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
        if (!is_subclass_of($this->getEntityClass(), '\\QF\\Mongo\\Entity')) {
            throw new \InvalidArgumentException('$entityClass must be an \\Mongo\\Entity instance or classname');
        }
    }
    
    public function getEntityClass()
    {
        return $this->entityClass ?: static::$defaultEntityClass;
    }

    /**
     *
     * @return MongoDB
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
     * @return \MongoCollection
     */
    public function getCollection()
    {
        $collectionName = $this->getCollectionName();
        return $this->getDB()->$collectionName;
    }
    
    /**
     *
     * @return string
     */
    public function getCollectionName()
    {
        $entityClass = $this->getEntityClass();
        return $entityClass::getCollectionName();
    }

    /**
     *
     * @param array $data initial data for the model
     * @param bool $isNew whether this is a new object (true, default) or loaded from database (false)
     * @return Entity
     */
    public function create($data = array(), $isNew = true)
    {
        $name = $this->getEntityClass();
        $entity = new $name($this->getDB());
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

    /**
     *
     * @param \MongoCursor|array $data initial data for the models
     * @param bool $single set to true if $data is a single model
     * @return array
     */
    public function build($data = array(), $single = false)
    {
        $return = array();
        if ($single) {
            $data = array($data);
        }
        
        $entityClass = $this->getEntityClass();
        
        foreach ($data as $current) {
            $model = $this->create($current, false);
            if (is_array($model->_id)) {
                $return[] = $model;
            } else {
                $return[(string) $model->_id] = $model;
            }
        }
        return $single ? reset($return) : $return;
    }

    /**
     *
     * @param mixed $query an array of fields for which to search or the _id as string or MongoId
     * @param bool $build (default true) set to false to return the raw MongoCursor
     * @return Entity the matching record or null
     */
    public function findOne($query, $build = true)
    {
        $entityClass = $this->getEntityClass();
        
        if (is_string($query)) {
            $query = array('_id' => $entityClass::isAutoId() ? new \MongoId($query) : $query);
        } elseif ($query instanceof \MongoId) {
            $query = array('_id' => $query);
        }
        $data = $this->getCollection()->findOne($query);
        if ($data) {
            return $this->build($data, true);
        }
    }

    /**
     *
     * @param array $query Associative array or object with fields to match.
     * @param int $limit Specifies an upper limit to the number returned.
     * @param int $skip Specifies a number of results to skip before starting the count.
     * @return int returns the number of documents matching the query.
     */
    public function count($query = array(), $limit = null, $skip = null)
    {
        return $this->getCollection()->count($query, $limit, $skip);
    }
    
    /**
     *
     * @param array $key the key(s) to get distinct values for
     * @param array $query Associative array or object with fields to match.
     * @return array returns the distinct values for the given key or false on error
     */
    public function distict($key = array(), $query = array())
    {
        $cmd = array(
            'distinct' => $this->getCollectionName(),
        );
        if ($key) {
            $cmd['key'] = $key;
        }
        if ($query) {
            $cmd['query'] = $query;
        }
        
        $result = $this->getDB()->command($cmd);
        
        if (!$result || empty($result['ok']) || !$result['ok'] || !isset($result['values'])) {
            return false;
        } else {
            return $result['values'];
        }
    }
    
    /**
     *
     * @param array $key the key(s) to group by 
     * @param array $condition Associative array or object with fields to match.
     * @param array $initial the initial value of the aggregator object
     * @param \MongoCode $reduce the reduce function as \MongoCode or string. example: function(obj,prev){ prev.count++;}
     * @param \MongoCode $finalize the finalize function as \MongoCode or string. example: function finalize(key, value) { return value; }
     * @return array returns the grouped data or false on error
     */
    public function group($key = array(), $condition = array(), $initial = array(), $reduce = null, $finalize = null)
    {
        $cmd = array(
            'ns' => $this->getCollectionName(),
        );
        
        $key = (array) $key;
        $tmpKey = array();
        foreach ($key as $k => $v) {
            if (is_integer($k) || $v !== true) {
                $tmpKey[$v] = true;
            } else {
                $tmpKey[$k] = true;
            }
        }
        $cmd['key'] = $tmpKey;
        
        if ($condition) {
            $cmd['condition'] = $condition;
        }
        if ($initial) {
            $cmd['initial'] = $initial;
        } else {
            $cmd['initial'] = array();
        }
        if ($reduce) {
            if (is_string($reduce)) {
                $reduce = new \MongoCode($reduce);
            }
            if (!is_object($reduce) || !($reduce instanceof \MongoCode)) {
                throw new \Exception('string or \\MongoCode expected for $reduce, ' . get_class($reduce) . ' given!');
            }
            $cmd['reduce'] = $reduce;
        }
        if ($finalize) {
            if (is_string($finalize)) {
                $finalize = new \MongoCode($finalize);
            }
            if (!is_object($finalize) || !($finalize instanceof \MongoCode)) {
                throw new \Exception('string or \\MongoCode expected for $finalize, ' . get_class($finalize) . ' given!');
            }
            $cmd['finalize'] = $finalize;
        }
        
        $result = $this->getDB()->command(array('group' => $cmd));
        
        if (!$result || empty($result['ok']) || !$result['ok'] || !isset($result['retval'])) {
            return false;
        } else {
            return $result['retval'];
        }
    }

    /**
     *
     * @param array $sort The fields by which to sort.
     * @param bool $build (default true) set to false to return the raw MongoCursor
     * @return \MongoCursor|array Returns an array or a cursor for the search results.
     */
    public function findAll($sort = array(), $build = true)
    {
        return $this->find(array(), $sort, null, null, $build);
    }

    /**
     *
     * @param array $query The fields for which to search.
     * @param array $sort The fields by which to sort.
     * @param int $limit The number of results to return.
     * @param int $skip The number of results to skip.
     * @param bool $build (default true) set to false to return the raw MongoCursor
     * @return \MongoCursor|array Returns an array or a cursor for the search results.
     */
    public function find($query = array(), $sort = array(), $limit = null, $skip = null, $build = true)
    {
        $cursor = $query ? $this->getCollection()->find($query) : $this->getCollection()->find();
        if ($sort) {
            $cursor->sort($sort);
        }
        if ($limit) {
            $cursor->limit($limit);
        }
        if ($skip) {
            $cursor->skip($skip);
        }

        return $build ? $this->build($cursor) : $cursor;
    }
    
    /**
     * $relations is an array of arrays as followed:
     *  array(fromAlias, relationProperty, toAlias, options = array())
     *      options is an array with the following optional keys:
     *          'query' => array additional query to filter
     *          'sort' => array sorting of the related entries
     *          'count' => false|string fetch only the number of related entries, not the entries themself
     * 
     *      if count=true, the count of related entities will be saved in the property of the from-object defined by count
     *      (example: 'count' => 'fooCount' will save the number of related entries in $fromObject->fooCount)
     *      
     * 
     * @param array $relations the relations
     * @param array $query The fields for which to search or the _id as string or MongoId
     * @param array $sort The fields by which to sort.
     * @return mixed Returns the first entity found or false
     */
    public function findOneWithRelations($relations = array(), $query = array(), $sort = array())
    {
        $entityClass = $this->getEntityClass();
        
        if (is_string($query)) {
            $query = array('_id' => $entityClass::isAutoId() ? new \MongoId($query) : $query);
        } elseif ($query instanceof \MongoId) {
            $query = array('_id' => $query);
        }
        
        $results = $this->findWithRelations($relations, $query, $sort, 1);
        return reset($results);
    }
    
    /**
     * $relations is an array of arrays as followed:
     *  array(fromAlias, relationProperty, toAlias, options = array())
     *      options is an array with the following optional keys:
     *          'query' => array additional query to filter
     *          'sort' => array sorting of the related entries
     *          'count' => false|string fetch only the number of related entries, not the entries themself
     * 
     *      if count=true, the count of related entities will be saved in the property of the from-object defined by count
     *      (example: 'count' => 'fooCount' will save the number of related entries in $fromObject->fooCount)
     *      
     * 
     * @param array $relations the relations
     * @param array $query The fields for which to search.
     * @param array $sort The fields by which to sort.
     * @param int $limit The number of results to return.
     * @param int $skip The number of results to skip.
     * @return array Returns an array or a cursor for the search results.
     */
    public function findWithRelations($relations = array(), $query = array(), $sort = array(), $limit = null, $skip = null)
    {
        $entityClasses = array();
        $entityClasses['a'] = $this->getEntityClass();

        if (!is_subclass_of($entityClasses['a'], '\\QF\\Mongo\\Entity')) {
            throw new \InvalidArgumentException('$entity must be an \\QF\\Mongo\\Entity instance or classname');
        }
        
        $cursor = $query ? $this->getCollection()->find($query) : $this->getCollection()->find();
        if ($sort) {
            $cursor->sort($sort);
        }
        if ($limit) {
            $cursor->limit($limit);
        }
        if ($skip) {
            $cursor->skip($skip);
        }
        
        $entities = array();
        $entities['a'] = $this->build($cursor);
        
        foreach ($relations as $rel) {
            if (empty($rel[0]) || !isset($entityClasses[$rel[0]])) {
                throw new \Exception('unknown fromAlias '.$rel[0]);
            }
            if (empty($rel[2])) {
                throw new \Exception('missing toAlias');
            }
            
            if (!is_subclass_of($entityClasses[$rel[0]], '\\QF\\Mongo\\Entity')) {
                throw new \InvalidArgumentException('$entity must be an \\QF\\Mongo\\Entity instance or classname');
            }
            
            if (empty($rel[1]) || !$relData = $entityClasses[$rel[0]]::getRelation($rel[1])) {
                throw new \Exception('unknown relation '.$rel[0].'.'.$rel[1]);
            }
            
            $entityClasses[$rel[2]] = $relData[0];
            
            $options = !empty($rel[3]) ? (array) $rel[3] : array();
            
            $fromValues = array();
            
            $repository = $relData[0]::getRepository($this->getDB());
            
            if (!empty($options['count'])) {
                foreach ($entities[$rel[0]] as $fromEntity) {
                    $fromEntity->countRelated($rel[1], (array) (!empty($options['query']) ? $options['query'] : array()), $options['count']);
                }
                continue;
            }
            
            if (isset($relData[3]) && $relData[3] === false && $relData[2] == '_id') {
                foreach ($entities[$rel[0]] as $fromEntity) {
                    $fromValues = array_merge($fromValues, (array) $fromEntity->get($relData[1]));
                }
            } else {
                foreach ($entities[$rel[0]] as $fromEntity) {
                    $fromValues[] = $fromEntity->get($relData[1]);
                }
            }
                    
            $query = array_merge(array($relData[2] => array('$in' =>  array_values($fromValues))), (array) (!empty($options['query']) ? $options['query'] : array())); 
            
            
            $entities[$rel[2]] = $repository->find($query, !empty($options['sort']) ? $options['sort'] : array());
                
            $entityTmp = array();
            
            foreach ($entities[$rel[0]] as $fk => $fromEntity) {
                foreach ($entities[$rel[2]] as $tk => $toEntity) {
                    if (!isset($entityTmp[0][$fk.'_'.$relData[1]])) {
                        $entityTmp[0][$fk.'_'.$relData[1]] = $fromEntity->get($relData[1]);
                    }
                    if (!isset($entityTmp[1][$tk.'_'.$relData[2]])) {
                        $entityTmp[1][$tk.'_'.$relData[2]] = $toEntity->get($relData[2]);
                    }
                    if (!empty($relData[3])) {
                        if ($entityTmp[0][$fk.'_'.$relData[1]] == $entityTmp[1][$tk.'_'.$relData[2]]) {
                            $fromEntity->set($rel[1], $toEntity);
                        }
                    } elseif(isset($relData[3])) {
                        if ($relData[1] == '_id' && in_array($entityTmp[0][$fk.'_'.$relData[1]], (array) $entityTmp[1][$tk.'_'.$relData[2]])) {
                            $fromEntity->add($rel[1], $toEntity);
                        } elseif(in_array($entityTmp[1][$tk.'_'.$relData[2]], (array) $entityTmp[0][$fk.'_'.$relData[1]])) {
                            $fromEntity->add($rel[1], $toEntity);
                        }
                    } else {
                        if ($entityTmp[0][$fk.'_'.$relData[1]] == $entityTmp[1][$tk.'_'.$relData[2]]) {
                            $fromEntity->add($rel[1], $toEntity);
                        }
                    }
                }
            }
        }
        
        return $entities['a'];
        
    }
    
    /**
     * creates a Mongo\MapReduce object for this collection
     * 
     * @param MongoCode|string $map the map function as MongoCode or string
     * @param MongoCode|string $reduce the reduce function as MongoCode or string
     * @param MongoCode|string $finalize the finalize function as MongoCode or string
     * @return MapReduce 
     */
    public function mapReduce($map = null, $reduce = null, $finalize = null)
    {
        return new MapReduce($this->getCollectionName(), $this->getDB(), $map, $reduce, $finalize);
    }
    
    /**
     *
     * @param array $update if raw = true: the update query. use array('$set' => array('key' => 'value'), '$unset' => array('key' => 1)) advanced dot-syntax is supproted for raw updates
     *                      if raw = false: array of key => value pairs to update (or key => null to unset)
     * @param array $query The fields for which to filter.
     * @param bool $multi update multiple documents (default = false)
     * @param bool|integer|string $w set to null to use connection default value / @see http://php.net/manual/en/mongo.writeconcerns.php
     * @param bool $raw true to remove the entries directly, false (default) to call remove() on each model 
     * @return bool if $raw is true, returns the status of the query. If $raw is false, it returns always true
     */
    public function updateBy($update, $query = array(), $multi = false, $w = null, $raw = false)
    {
        if ($raw) {
            $options = array();
            if ($multi) {
                $options['multi'] = true;
            }
            if ($w !== null) {
                $options['w'] = $w;
            }
            return $this->getCollection()->update($query, $update, $options);
        } else {
            foreach($this->find($query, array(), $multi ? null : 1, null) as $entity) {
                foreach ($update as $k => $v) {
                    $entity->$k = $v;
                }
                $entity->save($w);
            } 
            return true;
        }
    }
    
    /**
     *
     * @param array $query The fields for which to filter.
     * @param bool $justOne Remove at most one record matching this criteria.
     * @param bool|integer|string $w set to null to use connection default value / @see http://php.net/manual/en/mongo.writeconcerns.php
     * @param bool $raw true to remove the entries directly, false (default) to call remove() on each model 
     * @return bool if $raw is true, returns the status of the query. If $raw is false, it returns always true
     */
    public function removeBy($query = array(), $justOne = false, $w = null, $raw = false)
    {
        if ($raw) {
            $options = array();
            if ($justOne) {
                $options['justOne'] = true;
            }
            if ($w !== null) {
                $options['w'] = $w;
            }
            return $this->getCollection()->remove($query, $options);
        } else {
            foreach($this->find($query, array(), $justOne ? 1 : null, null) as $entity) {
                $entity->delete($w);
            } 
            return true;
        }
    }

    /**
     *
     * @param Entity $entity the model to save
     * @param bool|integer|string $w set to null to use conenction default value / @see http://php.net/manual/en/mongo.writeconcerns.php
     * @return bool returns if the update was successfully sent to the database.
     */
    public function save(Entity $entity, $w = true)
    {
        $entityClass = get_class($entity);
        
        try {
            if ($entity->preSave($this->getDB(), !$entity->isNew()) === false) {
                return false;
            }

            if ($entity->isNew()) {
                $insert = array();
                foreach ($entityClass::getColumns() as $column) {
                    if ($entity->get($column) !== null) {
                        $insert[$column] = $entity->get($column);
                    }
                }
                $status = $this->getCollection()->insert($insert, $w !== null ? array('w' => $w) : array());
                if ($status) {
                    if ($entityClass::isAutoId()) {
                        $entity->set('_id', $insert['_id']);
                    }
                    foreach ($insert as $key => $value) {
                        $entity->setDatabaseProperty($key, $value);
                    }
                    $entity->postSave($this->getDB(), false);
                    return true;
                }
                return false;
            } else {
                $query = array();
                foreach ($entityClass::getColumns() as $column) {
                    if ($entity->get($column) !== $entity->getDatabaseProperty($column)) {
                        if ($entity->get($column) === null) {
                            $query['$unset'][$column] = 1;
                        } else {
                            $query['$set'][$column] = $entity->get($column);
                        }
                    }
                }
                if (!count($query)) {
                    return true;
                }
                $status = $this->getCollection()->update(array('_id' => $entity->get('_id')), $query, $w !== null ? array('w' => $w) : array());
                if ($status) {
                    if (!empty($query['$set'])) {
                        foreach ($query['$set'] as $key => $value) {
                            $entity->setDatabaseProperty($key, $value);
                        }
                    }
                    if (!empty($query['$unset'])) {
                        foreach ($query['$unset'] as $key => $dummy) {
                            $entity->setDatabaseProperty($key, null);
                        }
                    }
                    $entity->postSave($this->getDB(), true);
                    return true;
                }
                return false;
            }
        } catch (\Exception $e) {
            //var_dump($e->getMessage());
            throw $e;
        }
    }

    /**
     *
     * @param Entity $entity the model to remove
     * @param bool|integer|string $w set to null to use conenction default value / @see http://php.net/manual/en/mongo.writeconcerns.php
     * @return mixed If "w" is not 0, returns an associative array with the status of the remove ("ok"), the number of items removed ("n"), and any error that may have occured ("err"). Otherwise, returns TRUE if the remove was successfully sent, FALSE otherwise.
     */
    public function remove(Entity $entity, $w = true)
    {
        if (!$entity->has('_id')) {
            return false;
        }
        try {
            if ($entity->preRemove($this->getDB()) === false) {
                return false;
            }
            $status = $this->getCollection()->remove(array('_id' => $entity->get('_id')), $w !== null ? array('w' => $w) : array());
            if ($status) {
                $entity->clearDatabaseProperties();
                $entity->postRemove($this->getDB());
                return true;
            }
            return false;
        } catch (Exception $e) {
            throw $e;
        }
    }

//    public function generateSlug($entry, $source, $field, $maxlength = 255)
//    {
//        $baseslug = MiniMVC_Registry::getInstance()->helper->text->sanitize($source, true);
//        $id = $entry->_id;
//        $num = 0;
//        $slug = $baseslug;
//        do {
//            if (mb_strlen($slug, 'UTF-8') > $maxlength) {
//                $baseslug = mb_substr($baseslug, 0, $maxlength - strlen((string) $num), 'UTF-8');
//                $slug = $baseslug . $num;
//            }
//
//            $result = $this->getCollection()->findOne(array($field => $slug), array('_id'));
//            $num--;
//        } while($result && (string)$result['_id'] != (string)$id && $slug = $baseslug . $num);
//        return $slug;
//    }

    /**
     * @param string $sb the database connection to use
     * @param string|Entity $entityClass
     * @return \QF\Mongo\Repository
     */
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