<?php
namespace QF\Mongo;

/**
 * @property mixed $property some property
 * 
 * @method mixed get*() get property
 * @method null set*(mixed $property) set property
 * @method bool is*() check if property is set
 * @method null clear*() clears/unsets property
 * 
 * @method null add*(mixed $property) adds a $property to property collection
 * @method null remove*(mixed $property) removes a $property from property collection
 * 
 * @method int count*(array $query = array(), mixed $saveAs = null) returns number of related property
 * @method array load*($query = array(), $sort = array(), $limit = null, $skip = null) loads related property
 * @method null link*(mixed $property, $load = true, $save = null) links related $property
 * @method null unlink*($property = true, $delete = false, $rawDelete = false, $save = null) unlinks related $property
 */
abstract class Entity extends \QF\Entity
{
    protected $_databaseProperties = array();
    protected $_db = null;
    
    protected static $columns = array();
    protected static $relations = array();

    protected static $maxDatabaseVersion = 0;
    
    protected static $collectionName = null;
    protected static $autoId = false;
    protected static $repositoryClass = '\\QF\\Mongo\\Repository';
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
                          //assumes 1:n or n:m relation if collection is set, 1:1 or n:1 otherwise
            'relationMultiple' => true //set to true for m:n relations (when either local_column or foreign_columns is an array) default = false
        ),
         */
        '_id'        => array('type' => '\\MongoId', 'column' => true),
    );
    
    /**
     * @var \MongoId
     */
    protected $_id;
    
    public function __construct($db = null)
    {
        $this->_db = $db;
    }
    
    public function __call($method, $args)
    {
        if (preg_match('/^(count|load|link|unlink)(.+)$/', $method, $matches)) {
            $action = $matches[1];
            $property = (isset(static::$_uncamelcased[$matches[2]]) ? static::$_uncamelcased[$matches[2]] : $this->_uncamelcase($matches[2]));
            if ($action == 'load') {
                return $this->loadRelated($property, isset($args[0]) ? $args[0] : array(), isset($args[1]) ? $args[1] : array(), isset($args[2]) ? $args[2] : null, isset($args[3]) ? $args[3] : null);
            } elseif ($action == 'count') {
                return $this->countRelated($property, isset($args[0]) ? $args[0] : array(), isset($args[1]) ? $args[1] : array());
            } elseif ($action == 'link') {
                return $this->linkRelated($property, isset($args[0]) ? $args[0] : null, array_key_exists(1, $args) ? $args[1] : true, array_key_exists(2, $args) ? $args[2] : false);
            } else {
                return $this->unlinkRelated($property, array_key_exists(0, $args) ? $args[0] : true, array_key_exists(1, $args) ? $args[1] : false, array_key_exists(2, $args) ? $args[2] : true);
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
     * @return MongoDB
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
     * @return \MongoCollection
     */
    public function getCollection($db = null)
    {
        if (!$db) {
            $db = $this->getDB();
        }
        return $this->getRepository($db)->getCollection();
    }

    public function increment($property, $value, $save = null)
    {
        $this->set($property, $this->get($property) + $value);
        if ($save !== false) {
            $status = $this->getCollection()->update(array('_id' => $this->_id), array('$inc' => array($property => $value)), $save !== null ? array('w' => $w) : array());
            if ($status) {
                $this->setDatabaseProperty($property, $this->$property);
                return true;
            }
            return false;
        }
        return true;
    }

    /**
     *
     * @param string $relation the relation name
     * @param array $query Additional fields to filter.
     * @param array $sort The fields by which to sort.
     * @param int $limit The number of results to return.
     * @param int $skip The number of results to skip.
     * @return Entity|array
     */
    public function loadRelated($relation, $query = array(), $sort = array(), $limit = null, $skip = null)
    {
        if (empty(static::$_properties[$relation])) {
            if (!empty(static::$_propertySingleNames[$relation])) {
                $relation = static::$_propertySingleNames[$relation];
            } else {
                foreach (static::$_properties as $prop => $data) {
                    if (!empty($data['collectionSingleName']) && $data['collectionSingleName'] == $relation) {
                        $relation = static::$_propertySingleNames[$relation] = $prop;          
                        break;
                    }
                }
            }
        }
        if (!$relationInfo = static::getRelation($relation)) {
            throw new \Exception('Unknown relation "'.$relation.'" for model '.get_class($this));
        }
        
        $repository = $relationInfo[0]::getRepository($this->getDB());
            
        if (!empty($relationInfo[3])) {
            $query = array_merge(array($relationInfo[2] => $this->get($relationInfo[1])), (array) $query);            
            $related = $repository->findOne($query);
            $this->set($relation, $related);
            return $related;
        } else {
            $query = (array) $query;
            if ($relationInfo[2] == '_id' && (isset($relationInfo[3]) && $relationInfo[3] === false)) {
                $query[$relationInfo[2]] = array('$in' => (array) $this->get($relationInfo[1]));
            } else {
                $query[$relationInfo[2]] = $this->get($relationInfo[1]);
            }            
            $related = $repository->find($query, $sort, $limit, $skip);
            $this->set($relation, $related);
            return $related;
        }
    }
    
    /**
     *
     * @param string $relation the relation name
     * @param array $query Additional fields to filter.
     * @param string $saveAs save the result in this property (Example: 'fooCount' to save as $this->fooCount) property must exist!
     * @return int
     */
    public function countRelated($relation, $query = array(), $saveAs = null)
    {
        if (empty(static::$_properties[$relation])) {
            if (!empty(static::$_propertySingleNames[$relation])) {
                $relation = static::$_propertySingleNames[$relation];
            } else {
                foreach (static::$_properties as $prop => $data) {
                    if (!empty($data['collectionSingleName']) && $data['collectionSingleName'] == $relation) {
                        $relation = static::$_propertySingleNames[$relation] = $prop;          
                        break;
                    }
                }
            }
        }
        if (!$relationInfo = static::getRelation($relation)) {
            throw new \Exception('Unknown relation "'.$relation.'" for model '.get_class($this));
        }
        
        $repository = $relationInfo[0]::getRepository($this->getDB());
            
        if (!empty($relationInfo[3])) {
            $query = array_merge(array($relationInfo[2] => $this->get($relationInfo[1])), (array) $query);            
            $return = $repository->count($query);
        } else {
            $query = (array) $query;
            if ($relationInfo[2] == '_id' && (isset($relationInfo[3]) && $relationInfo[3] === false)) {
                $query[$relationInfo[2]] = array('$in' => (array) $this->get($relationInfo[1]));
            } else {
                $query[$relationInfo[2]] = $this->get($relationInfo[1]);
            }            
            $return = $repository->count($query);
        }
        
        if ($saveAs) {
            $this->set($saveAs, $return);
        }
        return $return;
    }

    /**
     *
     * @param string $relation the relation name
     * @param Mongo_Model|mixed $related either a Mongo\Model object, a Mongo\Model->_id-value or an array with multiple Mongo\Models
     * @param bool $load also load the linked related entity to this entity
     * @param mixed $save set to false to prevent a save() call, otherwise call save($save)
     * @return bool
     */
    public function linkRelated($relation, $related, $load = true, $save = null)
    {
        if (empty(static::$_properties[$relation])) {
            if (!empty(static::$_propertySingleNames[$relation])) {
                $relation = static::$_propertySingleNames[$relation];
            } else {
                foreach (static::$_properties as $prop => $data) {
                    if (!empty($data['collectionSingleName']) && $data['collectionSingleName'] == $relation) {
                        $relation = static::$_propertySingleNames[$relation] = $prop;          
                        break;
                    }
                }
            }
        }
        if (!$relationInfo = static::getRelation($relation)) {
            throw new \Exception('Unknown relation "'.$relation.'" for model '.get_class($this));
        }
//        if (is_array($related)) {
//            foreach ($related as $rel) {
//                $this->linkRelated($relation, $rel, $load, $save);
//            }
//            return true;
//        }
        
        if (!empty($relationInfo[3])) {
            if (is_array($related)) {
                $related = array_pop($related);
            }
            if (!is_object($related) || !($related instanceof Entity)) {
                $repository = $relationInfo[0]::getRepository($this->getDB());
                $related = $repository->findOne($related);
                if (!$related) {
                    throw new \InvalidArgumentException('Could not find valid '.$relationInfo[0]);
                }
            }
            if ($relationInfo[1] == '_id') {
                if (!$this->has($relationInfo[1])) {
                    if (!static::isAutoId()) {
                        throw new \Exception('Could not link related '.$relationInfo[0].' - '.$relationInfo[1].' not set!');
                    }
                    $this->set($relationInfo[1], new \MongoId());
                    if ($save !== false) {
                        $this->save($save);
                    }
                }
                
                $related->set($relationInfo[2], $this->get($relationInfo[1]));
                return $save !== false ? $related->save($save) : true;
            } elseif ($relationInfo[2] == '_id') {
                if (!$related->has($relationInfo[2])) {
                    if (!$relationInfo[0]::isAutoId()) {
                        throw new \Exception('Counld not link realted '.$relationInfo[0].' - '.$relationInfo[2].' not set!');
                    }
                    $related->set($relationInfo[2], new \MongoId());
                    if ($save !== false) {
                        $related->save($save);
                    }
                }
                $this->set($relationInfo[1], $related->get($relationInfo[2]));
                return $save !== false ? $this->save($save) : true;
            }
            if ($load) {
                $this->set($relation, $related);
            }
        } elseif (is_array($related)) {
            if ($relationInfo[1] == '_id' && !$this->has($relationInfo[1])) {
                if (!static::isAutoId()) {
                    throw new \Exception('Couldnt not link realted '.$relationInfo[0].' - '.$relationInfo[1].' not set!');
                }
                $this->set($relationInfo[1], new \MongoId());
                if ($save !== false) {
                    $this->save($save);
                }
            }
            $relIDs = array();
            foreach ($related as $rel) {
                if (!is_object($rel) || !($related instanceof Entity)) {
                    $relIDs = is_object($rel) ? $rel : new \MongoId($rel);
                } else {
                    if (!$relationInfo[0]::isAutoId()) {
                        throw new \Exception('Couldnt not link realted '.$relationInfo[0].' - '.$relationInfo[2].' not set!');
                    }
                    $rel->set($relationInfo[2], new \MongoId());
                    if ($save !== false) {
                        $rel->save($save);
                    }
                }
                $relIDs[] = $rel;
            }
            if (isset($relationInfo[3]) && $relationInfo[3] === false) {
                $multiple = true;
            }
            if ($relationInfo[1] == '_id') {
                foreach ($relationInfo[0]::getRepository($this->getDB())->find(array('_id' => array('$in' => (array) $relIDs))) as $rel) {
                    if ($multiple) {
                        $rels = (array) $rel->get($relationInfo[2]);
                        if (!in_array($this->get($relationInfo[1]), $rels)) {
                            $rels[] = $this->get($relationInfo[1]);
                            $rels = array_values($rels);
                            $rel->set($relationInfo[2], $rels);
                        }
                    } else {
                        $rel->set($relationInfo[2], $this->get($relationInfo[1]));
                    }
                    if ($save !== false) {
                        $rel->save($save);
                    }
                    if ($load) {
                        $this->add($relation, $rel);
                    }
                }
                return true;   
            } else {
                if ($multiple) {
                    $rels = (array) $this->get($relationInfo[1]);
                    foreach ($relIDs as $rel) {
                        if (!in_array($rel, $rels)) {
                            $rels[] = $rel;
                        }
                    }
                    $rels = array_values($rels);
                    $this->set($relationInfo[1], $rels);
                    if ($load) {
                        foreach ($relationInfo[0]::getRepository($this->getDB())->find(array($relationInfo[2] => array('$in' => (array) $relIDs))) as $rel) {
                            $this->add($relation, $rel);
                        }
                    }
                } else {
                    $lastRel = array_pop($relIDs);
                    $this->set($relationInfo[1], $lastRel);
                    if ($load) {
                        $this->add($relation, $relationInfo[0]::getRepository($this->getDB())->findOne($lastRel));
                    }
                }
                
                return $save !== false ? $this->save($save) : true;
            }
        } else {
            if ($relationInfo[1] == '_id' && !$this->has($relationInfo[1])) {
                if (!static::isAutoId()) {
                    throw new \Exception('Couldnt not link realted '.$relationInfo[0].' - '.$relationInfo[1].' not set!');
                }
                $this->set($relationInfo[1], new \MongoId());
                if ($save !== false) {
                    $this->save($save);
                }
            } elseif ($relationInfo[2] == '_id' && !$related->has($relationInfo[2])) {
                if (!$relationInfo[0]::isAutoId()) {
                    throw new \Exception('Couldnt not link realted '.$relationInfo[0].' - '.$relationInfo[2].' not set!');
                }
                $related->set($relationInfo[2], new \MongoId());
                if ($save !== false) {
                    $related->save($save);
                }
            }
            if (isset($relationInfo[3]) && $relationInfo[3] === false) {
                $multiple = true;
            }
            if ($relationInfo[1] == '_id') {
                if ($multiple) {
                    $rels = (array) $related->get($relationInfo[2]);
                    if (!in_array($this->get($relationInfo[1]), $rels)) {
                        $rels[] = $this->get($relationInfo[1]);
                        $rels = array_values($rels);
                        $related->set($relationInfo[2], $rels);
                    }
                } else {
                    $related->set($relationInfo[2], $this->get($relationInfo[1]));                    
                }
                if ($load) {
                    $this->add($relation, $related);
                }
                return $save !== false ? $related->save($save) : true;
            } else {
                if ($multiple) {
                    $rels = (array) $this->get($relationInfo[1]);
                    if (!in_array($related->get($relationInfo[2]), $rels)) {
                        $rels[] = $related->get($relationInfo[2]);
                        $rels = array_values($rels);
                        $this->set($relationInfo[1], $rels);
                    }
                } else {
                    $this->set($relationInfo[1], $related->get($relationInfo[2]));
                }
                if ($load) {
                    $this->add($relation, $related);
                }
                return $save !== false ? $this->save($save) : true;
            }
        }
    }

    /**
     *
     * @param string $relation the relation name
     * @param \Mongo\Entity|mixed $related true to unlink all objects or either a \Mongo\Entity object, a \Mongo\Entity->_id-value  or an array with multiple \Mongo\Entity
     * @param boolean $delete true to delete the related entry, false to only remove the relation (default false) 
     * @param bool $rawDelete perform a raw/direct database delete instead of load and delete the related entries separately
     * @param mixed $save set to false to prevent a save() call, otherwise call save($save)
     * @return bool
     */
    public function unlinkRelated($relation, $related = true, $delete = false, $rawDelete = false, $save = null)
    {
        if (empty(static::$_properties[$relation])) {
            if (!empty(static::$_propertySingleNames[$relation])) {
                $relation = static::$_propertySingleNames[$relation];
            } else {
                foreach (static::$_properties as $prop => $data) {
                    if (!empty($data['collectionSingleName']) && $data['collectionSingleName'] == $relation) {
                        $relation = static::$_propertySingleNames[$relation] = $prop;          
                        break;
                    }
                }
            }
        }
        if (!$relationInfo = static::getRelation($relation)) {
            throw new \Exception('Unknown relation "'.$relation.'" for model '.get_class($this));
        }
        if (is_array($related)) {
            foreach ($related as $rel) {
                $this->unlinkRelated($relation, $rel, $delete, $rawDelete, $save);
            }
            return true;
        }
        if (!empty($relationInfo[3])) {
            
            $repository = $relationInfo[0]::getRepository($this->getDB());
                
            if ($relationInfo[1] == '_id') {
                if (!$this->has($relationInfo[1]) || $save === false) {
                    return true;
                }
                
                $query = array($relationInfo[2] => $this->get($relationInfo[1]));
                
                if ($related !== true) {
                    if (is_array($related)) {
                        $relIDs = array();
                        foreach ($related as $rel) {
                            if (!is_object($rel) || !($rel instanceof Entity)) {
                                $relIDs[] = is_object($rel) ? $rel : new \MongoId($rel);
                            } else {
                                $relIDs[] = $rel->get('_id');
                            }
                        }
                        $query['_id'] = array('$in' => $relIDs);
                    } else {
                        if (!is_object($related) || !($related instanceof Entity)) {
                            $query['_id'] = is_object($related) ? $related : new \MongoId($related);
                        } else {
                            $query['_id'] = $related->get('_id');
                        }
                    }
                }
                if ($delete) {
                    if (!$rawDelete) {
                        return $repository->removeBy($query, false, $save);
                    } else {
                        return $repository->getCollection()->remove($query, $options);
                    }
                } else {
                    if (!$rawDelete) {
                        return $repository->updateBy(array($relationInfo[2] => null), $query, true, $save);
                    } else {
                        return $repository->getCollection()->update($query, array('$set' => array($relationInfo[2] => null)), $options);
                    }
                }
            } else {
                if (is_array($related)) {
                    foreach ($related as $rel) {
                        if (is_object($rel) && $rel instanceof Entity) {
                            $rel = $rel->get('_id');
                        }
                        if ($this->get($relationInfo[1]) == $rel) {
                            $related = $rel;
                            break;
                        }
                    }
                }
                if ($related !== true) {
                    if (is_object($related) && $related instanceof Entity) {
                        $related = $related->get('_id');
                    }
                    if ($this->get($relationInfo[1]) != $related) {
                        return false;
                    }
                }
                
                if ($delete) {
                    $query = array($relationInfo[2] => $this->get($relationInfo[1]));
                    $options = $save !== false && $save !== null ? array('w' => $save) : array();
                    if (!$rawDelete) {
                        if (!$repository->removeBy($query, true, $safe)) {
                            return false;
                        } 
                    } else {
                        if (!$repository->getCollection()->remove($query, $options)) {
                            return false;
                        }    
                    }
                }
                $this->clear($relationInfo[1]);
                return $save !== false ? $this->save($save) : true;
            }
        } else {
            if (isset($relationInfo[3]) && $relationInfo[3] === false) {
                $multiple = true;
            }
            if ($related === true) {
                if ($relationInfo[2] == '_id') {                    
                    if ($delete) {
                        $repository = $relationInfo[0]::getRepository($this->getDB());
                        
                        if ($multiple) {
                            $status = (bool) $repository->removeBy(array($relationInfo[2] => array('$in' => $this->get($relationInfo[1]))), false, $save, $rawDelete);
                        } else {
                            $status = (bool) $repository->removeBy(array($relationInfo[2] => $this->get($relationInfo[1])), false, $save, $rawDelete);
                        }
                        
                        if (!$status) {
                            return false;
                        }                       
                    }
                    $this->clear($relationInfo[1]);
                    return $save !== false ? $this->save($save) : true;
                } else {
                    $repository = $relationInfo[0]::getRepository($this->getDB());
                    if (!$multiple || ($rawDelete && $delete)) {
                        $status = (bool) $repository->removeBy(array($relationInfo[2] => $this->get($relationInfo[1])), false, $save, $rawDelete);
                    } else {
                        if ($rawDelete) {
                            $repository->updateBy(array($relationInfo[2] => $this->get($relationInfo[1])), array('$unset' => array($relationInfo[2].'.$')), true, $save, $rawDelete);
                        } else {
                            $related = $repository->find(array($relationInfo[2] => $this->get($relationInfo[1])));
                            foreach ($related as $rel) {
                                $rels = $related->get($relationInfo[2]);
                                if ($k = array_search($this->get($relationInfo[1]), $rels)) {
                                    unset($rels[$k]);
                                    $rels = array_values($rels);
                                }
                                $rel->set($relationInfo[2], $rels);

                                if ($delete && !$rel->has($relationInfo[2]) && $save !== false) {
                                    $rel->delete($save);
                                } elseif ($save !== false) {
                                    $rel->save($save);
                                }
                            }
                        }
                    }
                }
                return true;
            } elseif (is_array($related)) {
                if ($relationInfo[2] == '_id') {   
                    $relIDs = array();
                    foreach ($related as $rel) {
                        if (!is_object($rel) || !($rel instanceof Entity)) {
                            $relIDs[] = is_object($rel) ? $rel : new \MongoId($rel);
                        } else {
                            $relIDs[] = $rel->get('_id');
                        }
                    }
                    if ($delete) {
                        $repository = $relationInfo[0]::getRepository($this->getDB());
                        
                        if ($multiple) {
                            $rels = $this->get($relationInfo[1]);
                            foreach ($relIDs as $k => $relID) {
                                if (!in_array($relID, $rels)) {
                                    unset($relIDs[$k]);
                                }
                            }
                            $relIDs = array_values($relIDs);
                            $status = (bool) $repository->removeBy(array($relationInfo[2] => array('$in' => $relIDs)), false, $save, $rawDelete);
                        } else {
                            if (!in_array($this->get($relationInfo[1]), $relIDs)) {
                                return false;
                            }
                            $status = (bool) $repository->removeBy(array($relationInfo[2] => $this->get($relationInfo[1])), false, $save, $rawDelete);
                        }
                        
                        if (!$status) {
                            return false;
                        }                       
                    }
                    if ($multiple) {
                        $rels = $this->get($relationInfo[1]);
                        foreach ($relIDs as $relID) {
                            if ($k = array_search($relID, $rels)) {
                                unset($rels[$k]);
                                $rels = array_values($rels);
                            }
                        }
                        $this->set($relationInfo[1], $rels);
                    } else {
                        if (in_array($this->get($relationInfo[1]), $relIDs)) {
                            $this->clear($relationInfo[1]);
                        }
                    }
                    return $save !== false ? $this->save($save) : true;
                } else {
                    $repository = $relationInfo[0]::getRepository($this->getDB());
                    if (!$multiple) {
                        $status = (bool) $repository->removeBy(array('_id' => array('$in' => $relIDs), $relationInfo[2] => $this->get($relationInfo[1])), false, $save, $rawDelete);
                    } else {
                        if ($rawDelete && $delete) {
                            $repository->removeBy(array('_id' => array('$in' => $relIDs), $relationInfo[2] => $this->get($relationInfo[1])), false, $safe === false ? null : $save, true);
                        } else {
                            $related = $repository->find(array('_id' => array('$in' => $relIDs), $relationInfo[2] => $this->get($relationInfo[1])));
                            foreach ($related as $rel) {
                                $rels = $related->get($relationInfo[2]);
                                if ($k = array_search($this->get($relationInfo[1]), $rels)) {
                                    unset($rels[$k]);
                                    $rels = array_values($rels);
                                }
                                $rel->set($relationInfo[2], $rels);

                                if ($delete && !$rel->has($relationInfo[2]) && $save !== false) {
                                    $rel->delete($save);
                                } elseif ($save !== false) {
                                    $rel->save($save);
                                }
                            }
                        }
                        
                    }
                }
                return true;
            } else {
                if (!is_object($related) || !($related instanceof Entity)) {
                    $repository = $relationInfo[0]::getRepository($this->getDB());
                    $related = $repository->findOne($related);
                }
                if (!$related) {
                    return false;
                }
                if ($related->get($relationInfo[2]) != $this->get($relationInfo[1]) && !is_array($related->get($relationInfo[2])) && !is_array($this->get($relationInfo[1]))) {
                    return false;
                }
                if ($relationInfo[1] == '_id') {
                    if ($multiple) {
                        $rels = $related->get($relationInfo[2]);
                        if ($k = array_search($this->get($relationInfo[1]), $rels)) {
                            unset($rels[$k]);
                            $rels = array_values($rels);
                        }
                        $related->set($relationInfo[2], $rels);
                    } elseif($related->get($relationInfo[2]) == $this->get($relationInfo[1])) {
                        $related->clear($relationInfo[2]);
                    } else {
                        return false;
                    }
                    if ($delete && !$related->has($relationInfo[2]) && $save !== false) {
                        $related->delete($save);
                    } elseif ($save !== false) {
                        return $related->save($save);
                    } else {
                        return true;
                    }
                } else {
                    if ($multiple) {
                        $rels = $this->get($relationInfo[1]);
                        if ($k = array_search($related->get($relationInfo[2]), $rels)) {
                            unset($rels[$k]);
                            $rels = array_values($rels);
                        }
                        $this->set($relationInfo[1], $rels);
                    } elseif($related->get($relationInfo[2]) == $this->get($relationInfo[1])) {
                        $this->clear($relationInfo[1]);
                    } else {
                        return false;
                    }
                    if ($delete && $save !== false) {
                        $related->delete($save);
                    } 
                    return $save !== false ? $this->save($save) : true;
                }
            }
        }
    }

    /**
     *
     * @param bool|integer|string $w set to null to use conenction default value / @see http://php.net/manual/en/mongo.writeconcerns.php
     * @return bool Returns if the update was successfully sent to the database.
     */
    public function save($w = null, $db = null)
    {
        if (!$db) {
            $db = $this->getDB();
        }
        try {
            return static::getRepository($db)->save($this, $w);
        } catch (\Exception $e) {
            throw $e;
            return false;
        }
    }

    /**
     *
     * @param bool|integer|string $w set to null to use conenction default value / @see http://php.net/manual/en/mongo.writeconcerns.php
     * @return mixed If "w" is not 0, returns an associative array with the status of the remove ("ok"), the number of items removed ("n"), and any error that may have occured ("err"). Otherwise, returns TRUE if the remove was successfully sent, FALSE otherwise.
     */
    public function delete($w = true, $db = null)
    {
        if (!$db) {
            $db = $this->getDB();
        }
        try {
            return static::getRepository($db)->remove($this, $w);
        } catch (\Exception $e) {
            throw $e;
            return false;
        }
    }
    
    /**
     * called before the entity is saved or updated in the db
     * return false (or throw exception) to abort
     * 
     * @param \MongoDB $db
     * @param bool $update if this save is an update (true) or an insert(false)
     */
    public function preSave(\MongoDB $db, $update)
    {

    }

    /**
     * called before the entity gets removed from db
     * return false (or throw exception) to abort
     * 
     * @param \MongoDB $db
     */
    public function preRemove(\MongoDB $db)
    {
        
    }
    
    /**
     * called after the entity was inserted or updated in the db
     * 
     * @param \MongoDB $db
     * @param bool $update if this save is an update (true) or an insert(false)
     */
    public function postSave(\MongoDB $db, $update)
    {
        
    }
    
    /**
     * called after the entity was removed from db
     * 
     * @param \MongoDB $db
     */
    public function postRemove(\MongoDB $db)
    {
        
    }

    /**
     * called after the entity was loaded from db
     * 
     * @param \MongoDB $db
     */
    public function postLoad(\MongoDB $db)
    {

    }
    
    public static function getMaxDatabaseVersion()
    {
        return static::$maxDatabaseVersion;
    }
    
    public static function getCollectionName()
    {
        return static::$collectionName;
    }
    
    public static function isAutoId()
    {
        return (bool) static::$autoId;
    }
    
    public static function getColumns()
    {
        if (!isset(static::$columns[static::$collectionName])) {
            $cols = array();        
            foreach (static::$_properties as $prop => $data) {
                if (!empty($data['column'])) {
                    $cols[] = $prop;
                }
            }
            static::$columns[static::$collectionName] = $cols;
        }
        return static::$columns[static::$collectionName];
    }
    
    public static function getRelation($relation)
    {
        if (!isset(static::$relations[static::$collectionName])) {
            $rels = array();          
            foreach (static::$_properties as $prop => $data) {
                if (!empty($data['relation']) && !empty($data['type']) && !empty($data['relation'][0]) && !empty($data['relation'][1])) {
                    $rel = array($data['type'], $data['relation'][0], $data['relation'][1]);
                    if (empty($data['collection'])) {
                        $rel[3] = true;
                    } elseif (!empty($data['relationMultiple'])) {
                        $rel[3] = false;
                    }
                    $rels[$prop] = $rel;
                }
            }
            static::$relations[static::$collectionName] = $rels;
        }
        return !empty(static::$relations[static::$collectionName][$relation]) ? static::$relations[static::$collectionName][$relation] : false;
    }
    
    public static function getRelations()
    {
        if (!isset(static::$relations[static::$collectionName])) {
            $rels = array();          
            foreach (static::$_properties as $prop => $data) {
                if (!empty($data['relation']) && !empty($data['type']) && !empty($data['relation'][0]) && !empty($data['relation'][1])) {
                    $rel = array($data['type'], $data['relation'][0], $data['relation'][1]);
                    if (empty($data['collection'])) {
                        $rel[3] = true;
                    }elseif (!empty($data['relationMultiple'])) {
                        $rel[3] = false;
                    }
                    $rels[$prop] = $rel;
                }
            }
            static::$relations[static::$collectionName] = $rels;
        }
        return static::$relations[static::$collectionName];
    }
    
    public static function getRepositoryClass()
    {
        return static::$repositoryClass;
    }
    
    /**
     * initiate the collection for this model
     */
    public static function install($db, $installedVersion = 0, $targetVersion = 0)
    {
        return false; //'no installation configured for this Entity';
        
        $collection = static::getRepository($db)->getCollection();
        
        if ($installedVersion <= 0 && $targetVersion >= 1) {
            //VERSION 0->1
           $collection->ensureIndex(array('slug' => 1), array('unique' => true));
        }
        
        if ($installedVersion <= 1 && $targetVersion >= 2) {
            //VERSION 1->2
            $collection->ensureIndex(array('name' => 1), array());
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
     * remove the collection for this model
     */
    public static function uninstall($db, $installedVersion = 0, $targetVersion = 0)
    {
        return false; //'no installation configured for this Entity';
        
        $collection = static::getRepository($db)->getCollection();
        
        //for every new Version, copy&paste this IF block and set MAX_VERSION to the new version
        /*
        if ($installedVersion >= MAX_VERSION && $targetVersion <= MAX_VERSION - 1) {
            //VERSION MAX_VERSION->MAX_VERSION-1
        }
        */
        
        if ($installedVersion >= 2 && $targetVersion <= 1) {
            //VERSION 2->1
            $collection->deleteIndex("name");
        }
        
        if ($installedVersion >= 1 && $targetVersion <= 0) {
            //VERSION 1->0
            $collection->drop();
        }
    }
    
}