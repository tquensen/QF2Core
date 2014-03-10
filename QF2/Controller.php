<?php
namespace QF;

class Controller
{
    /**
     * @var \Pimple the di container
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
     * @param \Pimple $container
     */
    public function setContainer(\Pimple $container)
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
