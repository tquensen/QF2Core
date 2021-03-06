<?php

namespace QF;

use \ArrayAccess;
use \Traversable;
use \Serializable;
use \IteratorAggregate;

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
 */
abstract class Entity implements ArrayAccess, Serializable, IteratorAggregate
{

    protected static $_types = array('boolean' => true, 'bool' => true, 'integer' => true, 'int' => true, 'float' => true, 'double' => true, 'string' => true, 'array' => true);
    protected static $_propertySingleNames = array();
    protected static $_camelcased = array();
    protected static $_uncamelcased = array();
    protected $_unserializing = false;
    protected static $_properties = array(
            /* example
              'property' => array(
                'type' => 'string', //a scalar type or a classname, true to allow any type, default = true
                'container' => 'data', //the parent-property containing the property ($this->container[$property]) or false ($this->$property), default = false
                'readonly' => false, //only allow read access (get, has, is)
                'required' => false, //disallow unset(), clear(), and set(null), default = false (unset(), clear(), and set(null) is allowed regardles of type) - the property can still be null if not initialized!
                'collection' => true, //stores multiple values, activates add and remove methods, true to store values in an array, name of a class that implements ArrayAccess to store values in that class, default = false (single value),
                'collectionUnique' => true, //do not allow dublicate entries when using as collection, when type = array or an object and collectionUnique is a string, that property/key will be used as index of the collection
                'collectionRemoveByValue' => true, //true to remove entries from a collection by value, false to remove by key, when type = array or an object and collectionRemoveByValue is a string, that property/key will be used to determine uniqueness, default = false, this only works if collection is an array or an object implementing Traversable
                'collectionSingleName' => false, //alternative property name to use for add/remove actions, default=false (e.g. if property = "children" and collectionSingleName = "child", you can use addChild/removeChild instead of addChildren/removeChildren)
                'exclude' => true, //set to true to exclude this property on toArray() and foreach(), default = false
                'default' => null // the default value to return by get if null, and to set by clear, default = null
              )
             */
    );

    public function get($property)
    {
        $method = 'get' . $this->_camelcase($property);
        if (method_exists($this, $method)) {
            return $this->$method();
        } else {
            return $this->getProperty($property);
        }
    }

    protected function getProperty($property)
    {
        if (empty(static::$_properties[$property])) {
            $trace = debug_backtrace();
            throw new \Exception('Trying to get undefined property: ' . get_class($this) . '::$' . $property .
            ' in ' . $trace[0]['file'] .
            ' on line ' . $trace[0]['line']);
        }

        if (!empty(static::$_properties[$property]['container'])) {
            if (!isset($this->{static::$_properties[$property]['container']}[$property])) {
                return isset(static::$_properties[$property]['default']) ? static::$_properties[$property]['default'] : null;
            }
            return $this->{static::$_properties[$property]['container']}[$property];
        } else {
            if ($this->$property === null && isset(static::$_properties[$property]['default'])) {
                return isset(static::$_properties[$property]['default']) ? static::$_properties[$property]['default'] : null;
            }
            return $this->$property;
        }
    }

    public function set($property, $value)
    {
        if ($value === null) {
            return $this->clear($property);
        }

        $method = 'set' . $this->_camelcase($property);
        if (method_exists($this, $method)) {
            return $this->$method($value);
        } else {
            return $this->setProperty($property, $value);
        }
    }

    protected function setProperty($property, $value)
    {
        if ($value === null) {
            return $this->clear($property);
        }

        if (empty(static::$_properties[$property])) {
            $trace = debug_backtrace();
            throw new \Exception('Trying to set undefined property: ' . get_class($this) . '::$' . $property .
            ' in ' . $trace[0]['file'] .
            ' on line ' . $trace[0]['line']);
        } elseif (!$this->_unserializing && !empty(static::$_properties[$property]['readonly'])) {
            $trace = debug_backtrace();
            throw new \Exception('Trying to set readonly property: ' . get_class($this) . '::$' . $property .
            ' in ' . $trace[0]['file'] .
            ' on line ' . $trace[0]['line']);
        }

        if (!empty(static::$_properties[$property]['collection'])) {
            if (is_string(static::$_properties[$property]['collection'])) {
                if (!is_object($value) || !($value instanceof static::$_properties[$property]['collection'])) {
                    $trace = debug_backtrace();
                    throw new \UnexpectedValueException('Error setting property: ' . get_class($this) . '::$' . $property .
                    ' must be of type ' . static::$_properties[$property]['type'] . ', ' . (is_object($value) ? get_class($value) : gettype($value)) . ' given in ' . $trace[0]['file'] .
                    ' on line ' . $trace[0]['line']);
                }
            } else {
                if (!is_array($value)) {
                    $trace = debug_backtrace();
                    throw new \UnexpectedValueException('Error setting property: ' . get_class($this) . '::$' . $property .
                    ' must be of type array, ' . (is_object($value) ? get_class($value) : gettype($value)) . ' given in ' . $trace[0]['file'] .
                    ' on line ' . $trace[0]['line']);
                }
            }
        } else {
            if (!empty(static::$_properties[$property]['type']) && is_string(static::$_properties[$property]['type'])) {
                if (isset(self::$_types[static::$_properties[$property]['type']])) {
                    if (gettype($value) != static::$_properties[$property]['type'] && (gettype($value) != 'double' || static::$_properties[$property]['type'] != 'float')) {
                        settype($value, static::$_properties[$property]['type']);
                    }
                } elseif (!is_object($value) || !($value instanceof static::$_properties[$property]['type'])) {
                    $trace = debug_backtrace();
                    throw new \UnexpectedValueException('Error setting property: ' . get_class($this) . '::$' . $property .
                    ' must be of type ' . static::$_properties[$property]['type'] . ', ' . (is_object($value) ? get_class($value) : gettype($value)) . ' given in ' . $trace[0]['file'] .
                    ' on line ' . $trace[0]['line']);
                }
            }
        }

        if (!empty(static::$_properties[$property]['container'])) {
            $this->{static::$_properties[$property]['container']}[$property] = $value;
        } else {
            $this->$property = $value;
        }
    }

    public function add($property, $value)
    {
        if (empty(static::$_properties[$property])) {
            if (!empty(static::$_propertySingleNames[$property])) {
                $property = static::$_propertySingleNames[$property];
            } else {
                foreach (static::$_properties as $prop => $data) {
                    if (!empty($data['collectionSingleName']) && $data['collectionSingleName'] == $property) {
                        $property = static::$_propertySingleNames[$property] = $prop;
                        break;
                    }
                }
            }
        }

        $method = 'add' . $this->_camelcase($property);
        if (method_exists($this, $method)) {
            return $this->$method($value);
        } else {
            return $this->addProperty($property, $value);
        }
    }

    protected function addProperty($property, $value)
    {
        if (empty(static::$_properties[$property])) {
            $trace = debug_backtrace();
            throw new \Exception('Trying to add to undefined property: ' . get_class($this) . '::$' . $property .
            ' in ' . $trace[0]['file'] .
            ' on line ' . $trace[0]['line']);
        } elseif (empty(static::$_properties[$property]['collection'])) {
            $trace = debug_backtrace();
            throw new \Exception('Trying to add to non-collection property: ' . get_class($this) . '::$' . $property .
            ' in ' . $trace[0]['file'] .
            ' on line ' . $trace[0]['line']);
        } elseif (!empty(static::$_properties[$property]['readonly'])) {
            $trace = debug_backtrace();
            throw new \Exception('Trying to add to readonly property: ' . get_class($this) . '::$' . $property .
            ' in ' . $trace[0]['file'] .
            ' on line ' . $trace[0]['line']);
        }

        if (!empty(static::$_properties[$property]['type']) && is_string(static::$_properties[$property]['type'])) {
            if (isset(self::$_types[static::$_properties[$property]['type']])) {
                if (gettype($value) != static::$_properties[$property]['type'] && (gettype($value) != 'double' || static::$_properties[$property]['type'] != 'float')) {
                    settype($value, static::$_properties[$property]['type']);
                }
            } elseif (!is_object($value) || !($value instanceof static::$_properties[$property]['type'])) {
                $trace = debug_backtrace();
                throw new \UnexpectedValueException('Error adding to property: ' . get_class($this) . '::$' . $property .
                ' must be of type ' . static::$_properties[$property]['type'] . ', ' . (is_object($value) ? get_class($value) : gettype($value)) . ' given in ' . $trace[0]['file'] .
                ' on line ' . $trace[0]['line']);
            }
        }

        if (!empty(static::$_properties[$property]['container'])) {
            if (!isset($this->{static::$_properties[$property]['container']}[$property])) {
                $this->{static::$_properties[$property]['container']}[$property] = static::$_properties[$property]['collection'] === true ? array() : new static::$_properties[$property]['collection'];
            }
            if (empty(static::$_properties[$property]['collectionUnique'])) {
                $this->{static::$_properties[$property]['container']}[$property][] = $value;
            } elseif (static::$_properties[$property]['collectionUnique'] === true) {
                $found = false;
                foreach ($this->{static::$_properties[$property]['container']}[$property] as $k => $v) {
                    if ($v === $value) {
                        unset($this->{static::$_properties[$property]['container']}[$property][$k]);
                        $this->{static::$_properties[$property]['container']}[$property][$k] = $value;
                        $found = true;
                    }
                }
                if (!$found) {
                    $this->{static::$_properties[$property]['container']}[$property][] = $value;
                }
            } else {
                if (is_array($value) || (is_object($value) && $value instanceof ArrayAccess)) {
                    $this->{static::$_properties[$property]['container']}[$property][$value[static::$_properties[$property]['collectionUnique']]] = $value;
                } elseif (is_object($value)) {
                    $this->{static::$_properties[$property]['container']}[$property][$value->{static::$_properties[$property]['collectionUnique']}] = $value;
                } else {
                    $trace = debug_backtrace();
                    throw new \UnexpectedValueException('Error adding to property: ' . get_class($this) . '::$' . $property .
                    ' must be of type array or object as CollectionUnique is set to ' . static::$_properties[$property]['collectionUnique'] . ' in ' . $trace[0]['file'] .
                    ' on line ' . $trace[0]['line']);
                }
            }
        } else {
            if (!isset($this->$property)) {
                $this->$property = static::$_properties[$property]['collection'] === true ? array() : new static::$_properties[$property]['collection'];
            }
            if (empty(static::$_properties[$property]['collectionUnique'])) {
                $this->{$property}[] = $value;
            } elseif (static::$_properties[$property]['collectionUnique'] === true) {
                $found = array_search($value, $this->$property, true);
                if ($found !== false) {
                    unset($this->{$property}[$found]);
                    $this->{$property}[$found] = $value;
                } else {
                    $this->{$property}[] = $value;
                }
            } else {
                if (is_array($value) || (is_object($value) && $value instanceof ArrayAccess)) {
                    $this->{$property}[$value[static::$_properties[$property]['collectionUnique']]] = $value;
                } elseif (is_object($value)) {
                    $this->{$property}[$value->{static::$_properties[$property]['collectionUnique']}] = $value;
                } else {
                    $trace = debug_backtrace();
                    throw new \UnexpectedValueException('Error adding to property: ' . get_class($this) . '::$' . $property .
                    ' must be of type array or object as CollectionUnique is set to ' . static::$_properties[$property]['collectionUnique'] . ' in ' . $trace[0]['file'] .
                    ' on line ' . $trace[0]['line']);
                }
            }
        }
    }

    public function remove($property, $value)
    {
        if (empty(static::$_properties[$property])) {
            if (!empty(static::$_propertySingleNames[$property])) {
                $property = static::$_propertySingleNames[$property];
            } else {
                foreach (static::$_properties as $prop => $data) {
                    if (!empty($data['collectionSingleName']) && $data['collectionSingleName'] == $property) {
                        $property = static::$_propertySingleNames[$property] = $prop;
                        break;
                    }
                }
            }
        }

        $method = 'remove'.$this->_camelcase($property);
        if (method_exists($this, $method)) {
            return $this->$method($value);
        } else {
            return $this->removeProperty($property, $value);
        }
    }
        
    protected function removeProperty($property, $value)
    {
        if (empty(static::$_properties[$property])) {
            $trace = debug_backtrace();
            throw new \Exception('Trying to remove from undefined property: ' . get_class($this) . '::$' . $property .
            ' in ' . $trace[0]['file'] .
            ' on line ' . $trace[0]['line']);
        } elseif (empty(static::$_properties[$property]['collection'])) {
            $trace = debug_backtrace();
            throw new \Exception('Trying to remove from non-collection property: ' . get_class($this) . '::$' . $property .
            ' in ' . $trace[0]['file'] .
            ' on line ' . $trace[0]['line']);
        } elseif (!empty(static::$_properties[$property]['readonly'])) {
            $trace = debug_backtrace();
            throw new \Exception('Trying to remove from readonly property: ' . get_class($this) . '::$' . $property .
            ' in ' . $trace[0]['file'] .
            ' on line ' . $trace[0]['line']);
        }

        if (!empty(static::$_properties[$property]['container'])) {
            if (empty($this->{static::$_properties[$property]['container']}[$property])) {
                return;
            }
            if (empty(static::$_properties[$property]['collectionRemoveByValue'])) {
                unset($this->{static::$_properties[$property]['container']}[$property]);
            } elseif (static::$_properties[$property]['collectionRemoveByValue'] === true) {
                if (is_object($this->{static::$_properties[$property]['container']}[$property]) && !($this->{static::$_properties[$property]['container']}[$property] instanceof Traversable)) {
                    $trace = debug_backtrace();
                    throw new \Exception('Trying to remove by value from collection that is not Traversable on property: ' . get_class($this) . '::$' . $property .
                    ' in ' . $trace[0]['file'] .
                    ' on line ' . $trace[0]['line']);
                } else {
                    foreach ($this->{static::$_properties[$property]['container']}[$property] as $k => $v) {
                        if ($v === $value) {
                            unset($this->{static::$_properties[$property]['container']}[$property][$k]);
                        }
                    }
                }
            } else {
                if (is_array($this->{static::$_properties[$property]['container']}[$property]) || (is_object($this->{static::$_properties[$property]['container']}[$property]) && $this->{static::$_properties[$property]['container']}[$property] instanceof Traversable)) {
                    foreach ($this->{static::$_properties[$property]['container']}[$property] as $k => $v) {
                        if (((is_array($v) && is_array($value)) || (is_object($v) && $v instanceof ArrayAccess && is_object($value) && $value instanceof ArrayAccess)) && $v[static::$_properties[$property]['collectionRemoveByValue']] === $value[static::$_properties[$property]['collectionRemoveByValue']]) {
                            unset($this->{static::$_properties[$property]['container']}[$property][$k]);
                        } elseif (is_object($v) && is_object($value) && $v->{static::$_properties[$property]['collectionRemoveByValue']} === $value->{static::$_properties[$property]['collectionRemoveByValue']}) {
                            unset($this->{static::$_properties[$property]['container']}[$property][$k]);
                        }
                    }
                }
            }
        } else {
            if (empty($this->$property)) {
                return;
            }
            if (empty(static::$_properties[$property]['collectionRemoveByValue'])) {
                unset($this->{$property}[$value]);
            } elseif (static::$_properties[$property]['collectionRemoveByValue'] === true) {
                if (is_object($this->$property) && !($this->$property instanceof Traversable)) {
                    $trace = debug_backtrace();
                    throw new \Exception('Trying to remove by value from collection that is not Traversable on property: ' . get_class($this) . '::$' . $property .
                    ' in ' . $trace[0]['file'] .
                    ' on line ' . $trace[0]['line']);
                } else {
                    $found = array_search($value, $this->$property, true);
                    if ($found !== false) {
                        unset($this->{$property}[$found]);
                    }
                }
            } else {
                if (is_array($this->$property) || (is_object($this->$property) && $this->$property instanceof Traversable)) {
                    foreach ($this->$property as $k => $v) {
                        if (((is_array($v) && is_array($value)) || (is_object($v) && $v instanceof ArrayAccess && is_object($value) && $value instanceof ArrayAccess)) && $v[static::$_properties[$property]['collectionRemoveByValue']] === $value[static::$_properties[$property]['collectionRemoveByValue']]) {
                            unset($this->{$property}[$k]);
                        } elseif (is_object($v) && is_object($value) && $v->{static::$_properties[$property]['collectionRemoveByValue']} === $value->{static::$_properties[$property]['collectionRemoveByValue']}) {
                            unset($this->{$property}[$k]);
                        }
                    }
                } else {
                    $trace = debug_backtrace();
                    throw new \Exception('Trying to remove by value from collection that is not Traversable on property: ' . get_class($this) . '::$' . $property .
                    ' in ' . $trace[0]['file'] .
                    ' on line ' . $trace[0]['line']);
                }
            }
        }
    }

    public function _unset($property)
    {
        return $this->clear($property);
    }

    public function clear($property)
    {
        $method = 'unset'.$this->_camelcase($property);
        if (method_exists($this, $method)) {
            return $this->$method();
        }
        $method = 'clear'.$this->_camelcase($property);
        if (method_exists($this, $method)) {
            return $this->$method();
        } else {
            return $this->clearProperty($property);
        }
    
    }
    
    public function clearProperty($property)
    {
        if (empty(static::$_properties[$property])) {
            $trace = debug_backtrace();
            throw new \Exception('Trying to unset undefined property: ' . get_class($this) . '::$' . $property .
            ' in ' . $trace[0]['file'] .
            ' on line ' . $trace[0]['line']);
        } elseif (!empty(static::$_properties[$property]['readonly'])) {
            $trace = debug_backtrace();
            throw new \Exception('Trying to unset readonly property: ' . get_class($this) . '::$' . $property .
            ' in ' . $trace[0]['file'] .
            ' on line ' . $trace[0]['line']);
        } elseif (!empty(static::$_properties[$property]['required'])) {
            $trace = debug_backtrace();
            throw new \Exception('Trying to unset required property: ' . get_class($this) . '::$' . $property .
            ' in ' . $trace[0]['file'] .
            ' on line ' . $trace[0]['line']);
        }

        if (!empty(static::$_properties[$property]['container'])) {
            $this->{static::$_properties[$property]['container']}[$property] = isset(static::$_properties[$property]['default']) ? static::$_properties[$property]['default'] : null;
        } else {
            $this->$property = isset(static::$_properties[$property]['default']) ? static::$_properties[$property]['default'] : null;
        }
    }

    public function is($property)
    {
        $method = 'is'.$this->_camelcase($property);
        if (method_exists($this, $method)) {
            return $this->$method();
        }
        $method = 'has'.$this->_camelcase($property);
        if (method_exists($this, $method)) {
            return $this->$method();
        } else {
            return $this->isProperty($property);
        }
    }
    
    public function isProperty($property)
    {
        if (empty(static::$_properties[$property])) {
            return false;
        }
        if (!empty(static::$_properties[$property]['container'])) {
            return !empty($this->{static::$_properties[$property]['container']}[$property]);
        } else {
            return !empty($this->$property);
        }
    }

    public function has($property)
    {
        return $this->is($property);
    }

    public function toArray($exclude = array(), $recursive = true)
    {
        $exclude = array_flip((array) $exclude);
        $return = array();
        foreach (array_keys(static::$_properties) as $prop) {
            if ((isset($exclude[$prop]) && (!$recursive || !is_array($exclude[$prop]))) || !empty(static::$_properties[$prop]['exclude'])) {
                continue;
            }
            $property = $this->get($prop);
            if ($recursive) {
                if (is_object($property) && $property instanceof Entity) {
                    $property = isset($exclude[$prop]) && is_array($exclude[$prop]) ? $property->toArray($exclude[$prop]) : $property->toArray();
                } elseif (is_array($property) || (is_object($property) && $property instanceof \Traversable)) {
                    foreach ($property as $k => $v) {
                        if (is_object($v) && $v instanceof Entity) {
                            $property[$k] = isset($exclude[$prop]) && is_array($exclude[$prop]) ? $v->toArray($exclude[$prop]) : $v->toArray();
                        }
                    }
                }
            }
            $return[$prop] = $property;
        }
        return $return;
    }

    public function getIterator()
    {
        return new \ArrayIterator($this->toArray(array(), false));
    }

    public function serialize()
    {
        $data = array();
        foreach (array_keys(static::$_properties) as $prop) {
            $data[$prop] = $this->get($prop);
        }
        return serialize($data);
    }

    public function unserialize($serialized)
    {
        $data = unserialize($serialized);
        $this->_unserializing = true; // workaround to set readonly properties
        foreach ($data as $k => $v) {
            $this->set($k, $v);
        }
        $this->_unserializing = false;
    }

    public function offsetSet($offset, $data)
    {
        $this->set($offset, $data);
    }

    public function offsetGet($offset)
    {
        return $this->get($offset);
    }

    public function offsetExists($offset)
    {
        return $this->is($offset);
    }

    public function offsetUnset($offset)
    {
        $this->clear($offset);
    }

    public function __get($property)
    {
        return $this->get($property);
    }

    public function __set($property, $value)
    {
        $this->set($property, $value);
    }

    public function __isset($property)
    {
        return $this->is($property);
    }

    public function __unset($property)
    {
        return $this->clear($property);
    }

    public function __call($method, $args)
    {
        if (preg_match('/^(get|set|clear|unset|has|is|add|remove)(.+)$/', $method, $matches)) {
            $action = $matches[1];
            $property = $this->_uncamelcase($matches[2]);
            if ($action == 'set' || $action == 'add' || $action == 'remove') {
                return $this->$action($property, isset($args[0]) ? $args[0] : null);
            } elseif ($action == 'unset') {
                return $this->clear($property);
            } else {
                return $this->$action($property);
            }
        } else {
            $trace = debug_backtrace();
            throw new \Exception('Call to undefined method: ' . get_class($this) . '::' . $method . '()' .
            ' in ' . $trace[0]['file'] .
            ' on line ' . $trace[0]['line']);
        }
    }

    protected function _uncamelcase($word)
    {
        return isset(self::$_uncamelcased[$word]) ? self::$_uncamelcased[$word] : (self::$_uncamelcased[$word] = strtolower(preg_replace('~(?<=\\w)([A-Z])~', '_$1', $word)));
    }

    protected function _camelcase($word)
    {
        return isset(self::$_camelcased[$word]) ? self::$_camelcased[$word] : (self::$_camelcased[$word] = str_replace(" ", "", ucwords(strtr($word, "_-", "  "))));
    }

}
