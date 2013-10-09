<?php
namespace QF;

class Controller implements ContainerAwareInterface
{
    /**
     *
     * @var \Pimple
     */
    protected $container;
    
    protected static $services = array();
    
    public function getContainer()
    {
        return $this->container;
    }

    public function setContainer(\Pimple $container)
    {
        $this->container = $container;
        foreach (static::$services as $param => $service) {
            if (is_numeric($param)) {
                $param = $service;
                $this->$param = $container[$service];
            }
        }
    }

}