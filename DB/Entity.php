<?php
namespace QF\DB;


abstract class Entity extends \QF\Entity
{
    protected $_databaseProperties = array();
    protected $_db = null;
    
    protected static $columns = array();
    protected static $relations = array();
    
    protected static $maxDatabaseVersion = 0;

    protected static $tableName = '';
    protected static $autoIncrement = false;
    protected static $identifier = 'id';
    protected static $repositoryClass = '\\QF\\DB\\Repository';
    protected static $_properties = array(
        /* example
        'property' => array(
            'type' => 'string', //a scalar type or a classname, true to allow any type, default = true
            'container' => 'data', //the parent-property containing the property ($this->container[$property]) or false ($this->$property), default = false
            'readonly' => false, //only allow read access (get, has, is)
            'required' => false, //disallow unset(), clear(), and set(null), default = false (unset(), clear(), and set(null) is allowed regardles of type) - the property can still be null if not initialized!
            'collection' => true, //stores multiple values, activates add and remove methods, true to store values in an array, name of a class that implements ArrayAccess to store values in that class, default = false (single value),
            'collectionUnique' => true, //do not allow dublicate entries when using as collection, when type = array or an object and collectionUnique is a string, that property/key will be used as index of the collection
            'collectionRemoveByValue' => true, //true to remove entries from a collection by value, false to remove by key, default = false, this only works if collection is an array or an object implementing Traversable
            'collectionSingleName' => false, //alternative property name to use for add/remove actions, default=false (e.g. if property = "children" and collectionSingleName = "child", you can use addChild/removeChild instead of addChildren/removeChildren)
            'exclude' => true, //set to true to exclude this property on toArray() and foreach(), default = false
            'default' => null, // the default value to return by get if null, and to set by clear, default = null
    
            'column' => true, //true if this property is a database column (default false)
            'relation' => array(local_column, foreign_column), //database relation or false for no relation, default = false
                          //assumes 1:n or n:m relation if collection is set, n:1 or 1:1 otherwise
            'refTable' => 'tablename' //for n:m relations - the name of the ref table, default = false
        )
         */
    );
    
    public function __construct($db = null)
    {
        $this->_db = $db;
    }
    
    public function __call($method, $args)
    {
        if (preg_match('/^(count|load|link|unlink)(.+)$/', $method, $matches)) {
            $action = $matches[1];
            $property = (isset(static::$_uncamelcased[$matches[2]]) ? static::$_uncamelcased[$matches[2]] : $this->_uncamelcase($matches[2]));
            if (static::getRelation($property)) {
                if ($action == 'load') {
                    return $this->loadRelated($property, isset($args[0]) ? $args[0] : null, isset($args[1]) ? $args[1] : array(), isset($args[2]) ? $args[2] : null, isset($args[3]) ? $args[3] : null, isset($args[4]) ? $args[4] : null);
                } elseif ($action == 'count') {
                    return $this->countRelated($property, isset($args[0]) ? $args[0] : null, isset($args[1]) ? $args[1] : array(), isset($args[2]) ? $args[2] : array());
                } elseif ($action == 'link') {
                    return $this->linkRelated($property, isset($args[0]) ? $args[0] : null);
                } else {
                    return $this->unlinkRelated($property, array_key_exists(0, $args) ? $args[0] : true, array_key_exists(1, $args) ? $args[1] : false);
                }
            }
        }
        return parent::__call($method, $args);
    }
    
    public function isNew()
    {
        return empty($this->_databaseProperties);
    }

    public function getDatabaseProperty($key)
    {
        return isset($this->_databaseProperties[$key]) ? $this->_databaseProperties[$key] : null;
    }

    public function setDatabaseProperty($key, $value)
    {
        $this->_databaseProperties[$key] = $value;
    }

    public function clearDatabaseProperties()
    {
        $this->_databaseProperties = array();
    }
    
    public function serialize()
    {
        $data = array();
        foreach (array_keys(static::$_properties) as $prop) {
            $data[$prop] = $this->get($prop);
        }
        return serialize(array(
            'p' => $data,
            'dbp' => $this->_databaseProperties
        ));
    }
    
    public function unserialize($serialized)
    {
        $data = unserialize($serialized);
        $this->__construct();
        $this->_unserializing = true;
        foreach ($data['p'] as $k => $v) {
            $this->set($k, $v);
        }
        $this->_unserializing = false;
        $this->_databaseProperties = $data['dbp'];
    }
    
    /**
     *
     * @return Repository
     */
    public static function getRepository($db)
    {
        return new static::$repositoryClass($db, get_called_class());
    }

    /**
     *
     * @return \PDO
     */
    public function getDB()
    {
        return $this->_db;
    }
    
    public function setDB($db)
    {
        $this->_db = $db;
    }
    
    /**
     *
     * @param string $relation the name of a relation
     * @param string $condition the where-condition
     * @param array $values values for the placeholders in the condition
     * @param string $order the order
     * @param int $limit the limit
     * @param int $offset the offset
     * @return Entity|array
     */
    public function loadRelated($relation, $condition = null, $values = array(), $order = null, $limit = null, $offset = null)
    {
        if (!$data = static::getRelation($relation)) {
            throw new \Exception('Unknown relation "'.$relation.'" for entity '.get_class($this));
        }
        if (!is_array($values)) {
            $values = (array) $values;
        }

        if (isset($data[3]) && $data[3] !== true) {

            $repository = $data[0]::getRepository($this->getDB());
            $stmt = $this->getDB()->prepare('SELECT '.$data[2].' FROM '.$data[3].' WHERE '.$data[1].'= ?')->execute(array($this->get(static::getIdentifier())));
            $refTableIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            
            $values = array_merge($refTableIds, (array) $values);
            $condition = (array) $condition;
            array_unshift($condition, $data[0]::getIdentifier().' IN ('.implode(',', array_fill(0, count($refTableIds), '?')).')');
            $entries = $repository->load($condition, $values, $order, $limit, $offset);
        } else {
            $values = (array) $values;
            array_unshift($values, $this->get($data[1]));
            $condition = (array) $condition;
            array_unshift($condition, $data[2].' = ?');
            $repository = $data[0]::getRepository($this->getDB());
            $entries = $repository->load($condition, $values, $order, $limit, $offset);
        }

        if (isset($data[3]) && $data[3] === true) {
            $entries = reset($entries);
            $this->set($relation, $entries);
        } else {
            foreach ($entries as $entry) {
                $this->add($relation, $entry);
            }
        }

        return $entries;
    }
    
    /**
     *
     * @param string $relation the name of a relation
     * @param string $condition the where-condition
     * @param array $values values for the placeholders in the condition
     * @param string $saveAs save the result in this property (Example: 'fooCount' to save as $this->fooCount) property must exist!
     * @return int
     */
    public function countRelated($relation, $condition = null, $values = array(), $saveAs = null)
    {
        if (!$data = static::getRelation($relation)) {
            throw new \Exception('Unknown relation "'.$relation.'" for entity '.get_class($this));
        }
        if (!is_array($values)) {
            $values = (array) $values;
        }

        if (isset($data[3]) && $data[3] !== true) {
            if (empty($condition) && empty($values)) {
                $stmt = $this->getDB()->prepare('SELECT count(a.'.$data[1].') FROM '.$data[3].' a LEFT JOIN '.$data[0]::getTableName().' b WHERE a.'.$data[1].' = ? AND b.'.$data[0]::getIdentifier().' IS NOT NULL')->execute(array($this->get(static::getIdentifier())));
                $return = $stmt->fetchColumn();
            } else {
                $repository = $data[0]::getRepository($this->getDB());
                $stmt = $this->getDB()->prepare('SELECT '.$data[2].' FROM '.$data[3].' WHERE '.$data[1].'= ?')->execute(array($this->get(static::getIdentifier())));
                $refTableIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);

                $values = array_merge($refTableIds, (array) $values);
                $condition = (array) $condition;
                array_unshift($condition, $data[0]::getIdentifier().' IN ('.implode(',', array_fill(0, count($refTableIds), '?')).')');
                $return = $repository->count($condition, $values);
            }
        } else {
            $values = (array) $values;
            array_unshift($values, $this->get($data[1]));
            $condition = (array) $condition;
            array_unshift($condition, $data[2].' = ?');
            $repository = $data[0]::getRepository($this->getDB());
            $return = $repository->count($condition, $values);
        }
        
        if ($saveAs) {
            $this->set($saveAs, $return);
        }
        return $return;
    }
    
    /**
     *
     * @param string $relation the name of a relation
     * @param mixed $identifier a related model object, the identifier of a related model or an array of those
     * @param bool $load also load the linked related entity to this entity
     * @param bool $rawUpdate perform a raw/direct database update instead of load and update the related entries separately
     */
    public function linkRelated($relation, $identifier = null, $load = true, $rawUpdate = false)
    {
//        if (is_array($identifier)) {
//            foreach ($identifier as $id) {
//                $this->linkRelated($relation, $id, $load, $rawUpdate);
//            }
//            return true;
//        }
        
        if (!$identifier) {
            throw new Exception('No identifier/related '.$relation.' given for model '.get_class($this));
        }
        if (!$data = static::getRelation($relation)) {
            throw new Exception('Unknown relation "'.$relation.'" for model '.get_class($this));
        }

        $repository = $data[0]::getRepository($this->getDB());
        if (!isset($data[3]) || $data[3] === true) {
            if ($data[1] == static::getIdentifier()) {
                if (!$this->has(static::getIdentifier())) {
                    $this->save();
                }
                if (is_object($identifier)) {
                    $identifier->set($data[2], $this->get(static::getIdentifier()));
                    $identifier->save();
                    if ($load) {
                        if (isset($data[3]) && $data[3] === true) {
                            $this->set($relation, $identifier);
                        } else {
                            $this->add($relation, $identifier);
                        }
                    }
                } elseif (is_array($identifier)) {
                    $idList = array();
                    foreach ($identifer as $id) {
                        $idList[] = is_object($id) ? $id->get($data[0]::getIdentifier()) : $id;
                    }
                    if (!$rawUpdate || $load) {
                        $relateds = $repository->load(array($data[0]::getIdentifier() . ' IN ('.implode(',', array_fill(0, count($idList), '?')).')'), $idList);
                        foreach ($relateds as $related) {
                            $related->set($data[2], $this->get(static::getIdentifier()));
                            $related->save();
                            if ($load) {
                                if (isset($data[3]) && $data[3] === true) {
                                    $this->$relation = $related;
                                } else {
                                    $this->add($relation, $related);
                                }
                            }
                        }
                    } else {
                        $this->getDB()->prepare('UPDATE '.$data[0]::getTableName().' SET '.$data[2].' = ? WHERE '.$data[0]::getIdentifier().' IN ('.implode(',', array_fill(0, count($idList), '?')).')')->execute(array_merge(array($this->get(static::getIdentifier())), $idList));
                    }
                } else {
                    if (!$rawUpdate || $load) {
                        $related = $repository->load(array($data[0]::getIdentifier() => $identifier));
                        if ($related) {
                            $related->set($data[2], $this->get(static::getIdentifier()));
                            $related->save();
                            if ($load) {
                                if (isset($data[3]) && $data[3] === true) {
                                    $this->set($relation, $related);
                                } else {
                                    $this->add($relation, $related);
                                }
                            }
                        }
                    } else {
                        $this->getDB()->prepare('UPDATE '.$data[0]::getTableName().' SET '.$data[2].' = ? WHERE '.$data[0]::getIdentifier().' = ?')->execute(array($this->get(static::getIdentifier()), $identifier));
                    }
                }
            } elseif ($data[2] == $data[0]::getIdentifier()) {
                if (is_array($identifier)) {
                    $identifier = array_pop($identifier);
                }
                if (is_object($identifier)) {
                    if (!$identifier->has($data[0]::getIdentifier())) {
                        $identifier->save();
                    }
                    $this->set($data[1], $identifier->get($data[0]::getIdentifier()));
                    if ($load) {
                        if (isset($data[3]) && $data[3] === true) {
                            $this->set($relation, $identifier);
                        } else {
                            $this->add($relation, $identifier);
                        }
                    }
                } else {
                    
                    $this->set($data[1], $identifier);
                    if ($load) {
                        $related = $repository->load(array($data[0]::getIdentifier() => $identifier));
                        if ($related) {
                            if (isset($data[3]) && $data[3] === true) {
                                $this->set($relation, $related);
                            } else {
                                $this->add($relation, $related);
                            }
                        }
                    }
                }
                $this->save();
            }
        } else {
            if (is_object($identifier)) {
                if (!$this->has(static::getIdentifier())) {
                    $this->save();
                }
                if (!$identifier->has($data[0]::getIdentifier())) {
                    $identifier->save();
                }
                $stmt = $this->getDB()->prepare('SELECT id, '.$data[1].', '.$data[2].' FROM '.$data[3].' WHERE '.$data[1].' = ? AND '.$data[2].' = ?')->execute(array($this->get(static::getIdentifier()), $identifier->get($data[0]::getIdentifier())));
                $result = $stmt->fetch(\PDO::FETCH_NUM);
                $stmt->closeCursor();
                if (!$result) {
                    $this->getDB()->prepare('INSERT INTO '.$data[3].' ('.$data[1].', '.$data[2].') VALUES (?,?)')->execute(array($this->get(static::getIdentifier()), $identifier->get($data[0]::getIdentifier())));
                    if ($load) {
                        $this->add($relation, $related);
                    }
                }
            } else {
                $idList = array();
                foreach ((array) $identifer as $id) {
                    $idList[] = is_object($id) ? $id->get($data[0]::getIdentifier()) : $id;
                }
                $stmt = $this->getDB()->prepare('SELECT '.$data[2].' FROM '.$data[3].' WHERE '.$data[1].' = ? AND '.$data[2].' IN ('.implode(',', array_fill(0, count($idList), '?')).')')->execute(array_merge(array($this->get(static::getIdentifier())), $idList));
                $result = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                $stmt->closeCursor();
                $realIdList = array();
                foreach ($idList as $id) {
                    if (!in_array($id, $result)) {
                        $realIdList[] = $id;
                    }
                }
                if ($realIdList) {
                    $condition = array();
                    $values = array();
                    foreach ($realIdList as $id) {
                        $condition[] = '(?,?)';
                        $values[] = $this->get(static::getIdentifier());
                        $values[] = $id;
                    }
                    $this->getDB()->prepare('INSERT INTO '.$data[3].' ('.$data[1].', '.$data[2].') VALUES '.  explode(',', $condition))->execute($values);
                    if ($load) {
                        $related = $repository->load(array($data[0]::getIdentifier() . ' IN ('.implode(',', array_fill(0, count($idList), '?')).')'), $idList);
                        if ($related) {
                            $this->add($relation, $related);
                        }
                    }
                }
                
            }
        }
    }
    
    /**
     *
     * @param string $relation the name of a relation
     * @param mixed $identifier a related model object, the identifier of a related model, an array of those or true to unlink all related models
     * @param bool $delete also delete the related entities
     * @param bool $rawDelete perform a raw/direc database delete instead of load and delete the related entries separately
     */
    public function unlinkRelated($relation, $identifier = true, $delete = false, $rawDelete = false)
    {
//        if (is_array($identifier)) {
//            foreach ($identifier as $id) {
//                $this->unlinkRelated($relation, $id, $delete, $rawDelete);
//            }
//            return true;
//        }

        if (!$data = static::getRelation($relation)) {
            throw new Exception('Unknown relation "'.$relation.'" for model '.get_class($this));
        }
        $repository = $data[0]::getRepository($this->getDB());
        if (!isset($data[3]) || $data[3] === true) {
            if ($data[1] == static::getIdentifier()) {
                if (is_object($identifier)) {                  
                    if ($delete) {
                        $identifier->delete();
                    } else {
                        $identifier->clear($data[2]);
                        $identifier->save();
                    }   
                } elseif(is_array($identifier)) {
                    if (!$this->has(static::getIdentifier())) {
                        return false;
                    }
                    $idList = array();
                    foreach ((array) $identifer as $id) {
                        $idList[] = is_object($id) ? $id->get($data[0]::getIdentifier()) : $id;
                    }
                    if (!$rawDelete) {
                        foreach ($repository->load(array($data[2] => $this->get(static::getIdentifier()), $data[0]::getIdentifier() . ' IN ('.implode(',', array_fill(0, count($idList), '?')).')'), $idList) as $related) {
                            if ($delete) {
                                $related->delete();
                            } else {
                                $related->clear($data[2]);
                                $related->save();
                            }
                        }
                    } else {
                        if ($delete) {
                            $this->getDB()->prepare('DELETE FROM '.$data[0]::getTableName().' WHERE '.$data[2].' = ? AND '.$data[0]::getIdentifier() . ' IN ('.implode(',', array_fill(0, count($idList), '?')).')')->execute(array_merge(array($this->get(static::getIdentifier())), $idList));
                        } else {
                            $this->getDB()->prepare('UPDATE '.$data[0]::getTableName().' SET '.$data[2].' = ? WHERE '.$data[2].' = ? AND '.$data[0]::getIdentifier() . ' IN ('.implode(',', array_fill(0, count($idList), '?')).')')->execute(array_merge(array(null, $this->get(static::getIdentifier())), $idList));
                        }
                    }
                } elseif($identifier === true) {
                    if (!$this->has(static::getIdentifier())) {
                        return false;
                    }
                    if (!$rawDelete) {
                        foreach ($repository->load(array($data[2] => $this->get(static::getIdentifier()))) as $related) {
                            if ($delete) {
                                $related->delete();
                            } else {
                                $related->{$data[2]} = null;
                                $related->save();
                            }
                        }
                    } else {
                        if ($delete) {
                            $this->getDB()->prepare('DELETE FROM '.$data[0]::getTableName().' WHERE '.$data[2].' = ?')->execute(array($this->get(static::getIdentifier())));
                        } else {
                            $this->getDB()->prepare('UPDATE '.$data[0]::getTableName().' SET '.$data[2].' = ? WHERE '.$data[2].' = ?')->execute(array(null, $this->get(static::getIdentifier())));
                        }
                    }
                } else {
                    if (!$this->has(static::getIdentifier())) {
                        return false;
                    }
                    if (!$rawDelete) {
                        $related = $repository->loadOne(array($data[0]::getIdentifier() => $identifier, $data[2] => $this->get(static::getIdentifier())));
                        if ($related) {
                            if ($delete) {
                                $related->delete();
                            } else {
                                $related->clear($data[2]);
                                $related->save();
                            }
                        }
                    } else {
                        if ($delete) {
                            $this->getDB()->prepare('DELETE FROM '.$data[0]::getTableName().' WHERE '.$data[0]::getIdentifier().' = ? AND '.$data[2].' = ?')->execute(array($identifier, $this->get(static::getIdentifier())));
                        } else {
                            $this->getDB()->prepare('UPDATE '.$data[0]::getTableName().' SET '.$data[2].' = ? WHERE '.$data[0]::getIdentifier().' = ? AND '.$data[2].' = ?')->execute(array(null, $identifier, $this->get(static::getIdentifier())));
                        }
                    }
                }
            } elseif ($data[2] == $data[0]::getIdentifier()) {
                $linkedIdentifier = $this->get($data[1]);
                if (is_array($identifier)) {
                    foreach ($identifier as $id) {
                        if (($identifier == $linkedIdentifier) || (is_object($identifier) && $identifier->get($data[0]::getIdentifier()) == $linkedIdentifier)) {
                            $identifier = $id;
                            break;
                        }
                    }
                }
                
                $this->clear($data[1]);
                $this->save();
                if ($delete) {
                    if (is_object($identifier) && $identifier->get($data[0]::getIdentifier()) == $linkedIdentifier) {
                        $identifier->delete();
                    } elseif ($linkedIdentifier && ($identifier === true || $identifier == $linkedIdentifier)) {
                        if (!$rawDelete) {
                            $related = $repository->loadOne(array($data[0]::getIdentifier() => $linkedIdentifier));
                            if ($related &&  $related->get($data[0]::getIdentifier()) == $linkedIdentifier) {
                                $related->delete();
                            }
                        } else {
                            $this->getDB()->prepare('DELETE FROM '.$data[0]::getTableName().' WHERE '.$data[0]::getIdentifier().' = ?')->execute(array($linkedIdentifier));
                        }
                    }
                }
            }
        } else {
            if (!$this->has(static::getIdentifier())) {
                return false;
            }
            if ($identifier === true) {
                if ($delete) {
                    $stmt = $this->getDB()->prepare('SELECT '.$data[2].' FROM '.$data[3].' WHERE '.$data[1].' = ?')->execute(array($this->get(static::getIdentifier())));
                    $refTableIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                    if (!$rawDelete) {
                        foreach($repository->load($data[0]::getIdentifier().' IN ('.implode(',', array_fill(0, count($refTableIds), '?')).')', $refTableIds) as $related) {
                            $related->delete();
                        } 
                    } else {
                        $this->getDB()->prepare('DELETE FROM '.$data[0]::getTableName().' WHERE '.$data[0]::getIdentifier().' IN ('.implode(',', array_fill(0, count($refTableIds), '?')).')')->execute($refTableIds);
                    }
                }
                $this->getDB()->prepare('DELETE FROM '.$data[3].' WHERE '.$data[1].' = ?')->execute(array($this->get(static::getIdentifier())));
            } elseif (is_array($identifier)) {
                $idList = array();
                foreach ((array) $identifer as $id) {
                    $idList[] = is_object($id) ? $id->get($data[0]::getIdentifier()) : $id;
                }
                if ($delete) {
                    $stmt = $this->getDB()->prepare('SELECT '.$data[2].' FROM '.$data[3].' WHERE '.$data[1].' = ? AND '.$data[2] . ' IN ('.implode(',', array_fill(0, count($idList), '?')).')')->execute(array_merge(array($this->get(static::getIdentifier())), $idList));
                    $refTableIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);
                    if (!$rawDelete) {
                        foreach($repository->load($data[0]::getIdentifier().' IN ('.implode(',', array_fill(0, count($refTableIds), '?')).')', $refTableIds) as $related) {
                            $related->delete();
                        } 
                    } else {
                        $this->getDB()->prepare('DELETE FROM '.$data[0]::getTableName().' WHERE '.$data[0]::getIdentifier().' IN ('.implode(',', array_fill(0, count($refTableIds), '?')).')')->execute($refTableIds);
                    }
                }
                $this->getDB()->prepare('DELETE FROM '.$data[3].' WHERE '.$data[1].' = ? AND '.$data[2] . ' IN ('.implode(',', array_fill(0, count($idList), '?')).')')->execute(array_merge(array($this->get(static::getIdentifier())), $idList));
            } else {
                if ($delete) {
                    if (is_object($identifier)) {
                        $identifier->delete();
                    } else {
                        $stmt = $this->getDB()->prepare('SELECT id, '.$data[1].', '.$data[2].' FROM '.$data[3].' WHERE '.$data[1].' = ? AND '.$data[2].' = ?')->execute(array($this->get(static::getIdentifier()), $identifier));
                        $result = $stmt->fetch(\PDO::FETCH_NUM);
                        $stmt->closeCursor();
                        if ($result) {
                            if (!$rawDelete) {
                                $related = $repository->loadOne(array($data[0]::getIdentifier() => $identifier));
                                if ($related) {
                                    $related->delete();
                                }
                            } else {
                                $this->getDB()->prepare('DELETE FROM '.$data[0]::getTableName().' WHERE '.$data[0]::getIdentifier().' = ? LIMIT 1')->execute(array($identifier));
                            }
                        }
                    }
                }
                $this->getDB()->prepare('DELETE FROM '.$data[3].' WHERE '.$data[1].' = ? AND '.$data[2].' = ?')->execute(array($this->get(static::getIdentifier()), is_object($identifier) ? $identifier->get($data[0]::getIdentifier()) : $identifier));
            }
        }
    }
    
    /**
     *
     * @return bool
     */
    public function save($db = null)
    {
        if (!$db) {
            $db = $this->getDB();
        }
        try {
            return static::getRepository($db)->save($this);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     *
     * @return bool.
     */
    public function delete($db = null)
    {
        if (!$db) {
            $db = $this->getDB();
        }
        try {
            return static::getRepository($db)->remove($this);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * called before the entity is saved or updated in the db
     * return false (or throw exception) to abort
     * 
     * @param \PDO $db
     * @param bool $update if this save is an update (true) or an insert(false)
     */
    public function preSave(\PDO $db, $update)
    {

    }

    /**
     * called before the entity gets removed from db
     * return false (or throw exception) to abort
     * 
     * @param \PDO $db
     */
    public function preRemove(\PDO $db)
    {
        
    }
    
    /**
     * called after the entity was inserted or updated in the db
     * 
     * @param \PDO $db
     * @param bool $update if this save is an update (true) or an insert(false)
     */
    public function postSave(\PDO $db, $update)
    {
        
    }
    
    /**
     * called after the entity was removed from db
     * 
     * @param \PDO $db
     */
    public function postRemove(\PDO $db)
    {
        
    }

    /**
     * called after the entity was loaded from db
     * 
     * @param \PDO $db
     */
    public function postLoad(\PDO $db)
    {

    }
    
    public static function getMaxDatabaseVersion()
    {
        return static::$maxDatabaseVersion;
    }
    
    public static function getIdentifier()
    {
        return static::$identifier;
    }
    
    public static function getTableName()
    {
        return static::$tableName;
    }
    
    public static function isAutoIncrement()
    {
        return (bool) static::$autoIncrement;
    }
    
    public static function getColumns($prefix = null)
    {
        if (!isset(static::$columns[static::$tableName])) {
            $cols = array();        
            foreach (static::$_properties as $prop => $data) {
                if (!empty($data['column'])) {
                    $cols[] = $prop;
                }
            }
            static::$columns[static::$tableName] = $cols;
        }
        if ($prefix) {
            $cols = array();
            foreach (static::$columns[static::$tableName] as $col) {
                $cols[] = $prefix.'.'.$col.' '.$prefix.'_'.$col;
            }
            return $cols;
        }
        return static::$columns[static::$tableName];
    }
    
    public static function getRelation($relation)
    {
        if (!isset(static::$relations[static::$tableName])) {
            $rels = array();          
            foreach (static::$_properties as $prop => $data) {
                if (!empty($data['relation']) && !empty($data['type']) && !empty($data['relation'][0]) && !empty($data['relation'][1])) {
                    $rel = array($data['type'], $data['relation'][0], $data['relation'][1]);
                    if (!empty($data['refTable'])) {
                        $rel[3] = $data['refTable'];
                    } elseif (empty($data['collection'])) {
                        $rel[3] = true;
                    }
                    $rels[$prop] = $rel;
                }
            }
            static::$relations[static::$tableName] = $rels;
        }
        return !empty(static::$relations[static::$tableName][$relation]) ? static::$relations[static::$tableName][$relation] : false;
    }
    
    public static function getRelations()
    {
        if (!isset(static::$relations[static::$tableName])) {
            $rels = array();          
            foreach (static::$_properties as $prop => $data) {
                if (!empty($data['relation']) && !empty($data['type']) && !empty($data['relation'][0]) && !empty($data['relation'][1])) {
                    $rel = array($data['type'], $data['relation'][0], $data['relation'][1]);
                    if (!empty($data['refTable'])) {
                        $rel[3] = $data['refTable'];
                    } elseif (empty($data['collection'])) {
                        $rel[3] = true;
                    }
                    $rels[$prop] = $rel;
                }
            }
            static::$relations[static::$tableName] = $rels;
        }
        return static::$relations[static::$tableName];
    }
    
    public static function getRepositoryClass()
    {
        return static::$repositoryClass;
    }
    
    /**
     * Created the table for this model
     */
    public static function install($db, $installedVersion = 0, $targetVersion = 0)
    {
        return false; //'no installation configured for this Entity';

        if ($installedVersion <= 0 && $targetVersion >= 1) {
            //VERSION 0->1
            $sql = "CREATE TABLE " . static::getTableName() . " (
                      " . static::getIdentifier() . " INT(11) " . (static::isAutoIncrement() ? "AUTO_INCREMENT" : "") . ",
                          
                      slug VARCHAR(255) NOT NULL,
                      foo TINYINT(1) NOT NULL,
                      
                      PRIMARY KEY (" . static::getIdentifier() . "),
                      UNIQUE KEY slug (slug),
                      KEY foo (foo)
                      
                    ) ENGINE=INNODB DEFAULT CHARSET=utf8";

            $db->query($sql);
        }

        if ($installedVersion <= 1 && $targetVersion >= 2) {
            //VERSION 1->2
            $sql = "ALTER TABLE " . static::getTableName() . "
                        ADD something VARCHAR(255)";

            $db->query($sql);
        }

        //for every new Version, copy&paste this IF block and set MAX_VERSION to the new version
        /*
          if ($installedVersion <= MAX_VERSION - 1 && $targetVersion >= MAX_VERSION) {
          //VERSION MAX_VERSION-1->MAX_VERSION
          }
         */

        return true;
    }

    /**
     * Deletes the table for this model
     */
    public static function uninstall($db, $installedVersion = 0, $targetVersion = 0)
    {
        return false; //'no installation configured for this Entity';
        
        //for every new Version, copy&paste this IF block and set MAX_VERSION to the new version
        /*
        if ($installedVersion >= MAX_VERSION && $targetVersion <= MAX_VERSION - 1) {
            //VERSION MAX_VERSION->MAX_VERSION-1
        }
        */
        
        if ($installedVersion >= 2 && $targetVersion <= 1) {
            //VERSION 2->1
            $sql = "ALTER TABLE ".static::getTableName()." DROP something";
            $db->query($sql);
        }
        
        if ($installedVersion >= 1 && $targetVersion <= 0) {
            //VERSION 1->0
            $sql = "DROP TABLE ".static::getTableName()."";
            $db->query($sql);
        }
        
        return true;
    }
}