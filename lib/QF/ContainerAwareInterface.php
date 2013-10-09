<?php
namespace QF;

interface ContainerAwareInterface
{  
    abstract public function getContainer();
    abstract public function setContainer(\Pimple $container);
}