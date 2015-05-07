<?php
namespace QF;

class Controller
{
    /**
     * @var \Pimple\Container the di container
     */
    protected $container = null;
            
    /**
     * 
     * @return \Pimple
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * 
     * @param \Pimple\Container $container
     */
    public function setContainer(\Pimple\Container $container)
    {
        $this->container = $container;
    }

    /**
     * 
     * @param string $service
     * @return mixed
     */
    protected function getService($service)
    {
        return $this->container[$service];
    }
    
}
