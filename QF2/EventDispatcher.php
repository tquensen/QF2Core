<?php

namespace QF;

class EventDispatcher
{

    protected $container = null;
    protected $listerners = array();
    protected $unloadedListeners = array();

    public function __construct($c = null, $listeners = array())
    {
        $this->setContainer($c);
        $this->unloadedListeners = $listeners;
    }

    public function notify($eventName, Event $event = null)
    {
        if ($event === null) {
            $event = new Event();
        }
        
        $event->setEventDispatcher($this);
        $event->setName($eventName);
        
        foreach ($this->getListeners($eventName) as $listener) {
            call_user_func($listener, $event);
            if ($event->isPropagationStopped()) {
                break;
            }
        }

        return $event;
    }

    public function getListeners($event)
    {
        $this->loadListeners($event);
        krsort($this->listeners[$event]);
        return call_user_func_array('array_merge', $this->listeners[$event]);
    }

    public function addListener($event, $listener, $priority = 0)
    {
        $this->listeners[$event][$priority][] = $listener;
    }

    public function removeListener($event, $listener)
    {
        if (empty($this->listeners[$event])) {
            return;
        }

        foreach ($this->listeners[$event] as $prio => $listeners) {
            if ($k = array_search($listener, $listeners, true)) {
                unset($this->listeners[$event][$prio][$k]);
            }
        }
    }

    public function getContainer()
    {
        return $this->container;
    }

    public function setContainer($container)
    {
        $this->container = $container;
    }

    protected function loadListeners($event)
    {
        if (!isset($this->listeners[$event])) {
            $this->listeners[$event] = array();
        }

        if (empty($this->unloadedListeners[$event])) {
            return;
        }

        foreach ($this->unloadedListeners[$event] as $listener) {
            $priority = isset($listener['priority']) ? (int) $listener['priority'] : 0;

            $this->addListener($event, array($this->container[$listener['service']], $listener['method']), $priority);
        }
        
        $this->unloadedListeners[$event] = array();
    }

}