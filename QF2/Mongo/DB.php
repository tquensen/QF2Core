<?php
namespace QF\Mongo;

class DB
{
    protected $connections = array();
    protected $settings = array();
    protected $defaultConnection = 'default';
    
    public function __construct($settings = array())
    {
        $this->settings = $settings;
    }
    
    /**
     *
     * @param string $connection the connection name or null for default connection
     * @return \MongoDB
     */
    public function get($connection = NULL)
    {
        if ($connection === NULL) {
            $connection = $this->getDefaultConnection();
        }
        if (!isset($this->connections[$connection])) {
            if (!array_key_exists($connection, $this->settings)) {
                throw new Exception('Unknown connection '.$connection);
            }
            if (!empty($this->settings[$connection]['server']) || !empty($this->settings[$connection]['options'])  || !empty($this->settings[$connection]['driverOptions'])) {
                $mongo = new \MongoClient(!empty($this->settings[$connection]['server']) ? $this->settings[$connection]['server'] : 'mongodb://localhost:27017', !empty($this->settings[$connection]['options']) ? $this->settings[$connection]['options'] : array(), !empty($this->settings[$connection]['driverOptions']) ? $this->settings[$connection]['driverOptions'] : array());
            } else {
                $mongo = new \MongoClient();
            }
            $database = !empty($this->settings[$connection]['database']) ? $this->settings[$connection]['database'] : 'default';
            $this->connections[$connection] = $mongo->$database;
        }
        return $this->connections[$connection];
    }
    
    /**
     *
     * @param string|\QF\Mongo\Entity $entityClass (name of) an entity class
     * @param string $connection the connection name or null for default connection
     * @return \QF\Mongo\Repository 
     */
    public function getRepository($entityClass, $connection = NULL)
    {
        if ($connection === NULL) {
            $connection = $this->getDefaultConnection();
        }
        
        if (is_object($entityClass)) {
            $entityClass = get_class($entityClass);
        }
            
        if (!is_subclass_of($entityClass, '\\QF\\Mongo\\Entity')) {
            throw new \InvalidArgumentException('$entityClass must be an \\QF\\Mongo\\Entity instance or classname');
        }
        
        return $entityClass::getRepository($this->get($connection));
    }

    function getDefaultConnection()
    {
        return $this->defaultConnection;
    }

    function setDefaultConnection($defaultConnection)
    {
        $this->defaultConnection = $defaultConnection;
    }


    
}