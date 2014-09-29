<?php
namespace QF\DB;

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
     * @return \PDO
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
            
            $pdo = new \PDO(
                $this->settings[$connection]['driver'],
                isset($this->settings[$connection]['username']) ? $this->settings[$connection]['username'] : '',
                isset($this->settings[$connection]['password']) ? $this->settings[$connection]['password'] : '',
                isset($this->settings[$connection]['options']) ? $this->settings[$connection]['options'] : array()
            );
            
            if ($pdo && $pdo->getAttribute(\PDO::ATTR_DRIVER_NAME) == 'mysql') {
                $pdo->exec('SET CHARACTER SET utf8');
            }
            
            $this->connections[$connection] = $pdo;
        }
        return $this->connections[$connection];
    }
    
    /**
     *
     * @param string|\QF\DB\Entity $entityClass (name of) an entity class
     * @param string $connection the connection name or null for default connection
     * @return \QF\DB\Repository 
     */
    public function getRepository($entityClass, $connection = NULL)
    {
        if ($connection === NULL) {
            $connection = $this->getDefaultConnection();
        }

        if (is_object($entityClass)) {
            $entityClass = get_class($entityClass);
        }
            
        if (!is_subclass_of($entityClass, '\\QF\\DB\\Entity')) {
            throw new \InvalidArgumentException('$entityClass must be an \\QF\\DB\\Entity instance or classname');
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
