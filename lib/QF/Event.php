<?php

namespace QF;

class Event implements \ArrayAccess, \IteratorAggregate
{
    
    protected $propagationStopped = false;
    protected $eventDispatcher = null;
    protected $name = '';
    protected $value = null;
    
    protected $subject = null;
    
    protected $properties = array();

    public function __construct($subject = null, $properties = array(), $value = null)
    {
        $this->subject = $subject;
        $this->properties = $properties;
        $this->value = $value;
    }
    
    public function isPropagationStopped()
    {
        return $this->propagationStopped;
    }
    
    public function stopPropagation()
    {
        $this->propagationStopped = true;
    }
    
    public function getEventDispatcher()
    {
        return $this->eventDispatcher;
    }

    public function setEventDispatcher($eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getValue()
    {
        return $this->value;
    }

    public function setValue($value, $final = false)
    {
        $this->value = $value;
        if ($final) {
            $this->stopPropagation();
        }
    }

    public function getSubject()
    {
        return $this->subject;
    }

    public function setSubject($subject)
    {
        $this->subject = $subject;
    }

        
    public function getProperties()
    {
        return $this->properties;
    }

    public function setProperties($properties)
    {
        $this->properties = $properties;
    }
    
    public function getProperty($property)
    {
        return isset($this->properties[$property]) ? $this->properties[$property] : null;
    }
    
    public function setProperty($property, $value)
    {
        $this->properties[$property] = $value;
    }
    
    public function hasProperty($property)
    {
        return isset($this->properties[$property]);
    }
    
    public function getIterator()
    {
        return new \ArrayIterator($this->properties);
    }
    
    public function offsetGet($property)
    {
        return $this->getProperty($property);
    }

    public function offsetSet($property, $value)
    {
        $this->setProperty($property, $value);
    }

    public function offsetUnset($property)
    {
        $this->setProperty($property, null);
    }

    public function offsetExists($property)
    {
        return $this->hasProperty($property);
    }

    public function __get($property)
    {
        return $this->getProperty($property);
    }
    
    public function __set($property, $value)
    {
        $this->setProperty($property, $value);
    }
    
    public function __isset($property) {
        return $this->hasProperty($property);
    }
    
    public function __unset($property) {
        $this->setProperty($property, null);
    }
}