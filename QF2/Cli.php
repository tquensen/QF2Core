<?php
namespace QF;

class Cli
{

    /**
     *
     * @var \Pimple\Container
     */
    protected $container = null;
    
    protected $tasks = null;
    
    public function __construct($tasks)
    {
        $this->tasks = $tasks;
    }

    /**
     * calls the task defined by $class and $task and returns the output
     *
     * @param string $service the service to call
     * @param string $task the task name
     * @param array $parameter parameters for the task
     * @return string the parsed output of the task
     */
    public function callTask($service, $task, $parameter = array())
    {
        $c = $this->getContainer();
        $class = $c[$service];
        
        if (!method_exists($class, $task)) {
            throw new \Exception('task '.$class.'->'.$task.' not found');
        }

        return $class->$task($parameter);
    }

    /**
     *
     * @param array $argv the raw CLI parameters to parse
     * @return array an array containing the task data
     */
    public function parseArgs($argv)
    {
        array_shift($argv); // remove the filename (cli.php)

        $out = array();
        $task = array_shift($argv);

        if (!$task || !$taskData = $this->getTask($task)) {
            throw new \Exception('task '.$task.' not found');
        }
        if (empty($taskData['service']) || empty($taskData['task'])) {
            throw new \Exception('task not found');
        }

        for ($i = 0, $j = count($argv); $i < $j; $i++) {
            $arg = $argv[$i];

            // --foo --bar=baz
            if (substr($arg, 0, 2) == '--') {
                $eqPos = strpos($arg, '=');
                // --foo
                if ($eqPos === false) {
                    $key = substr($arg, 2);
                    // --foo value
                    if ($i + 1 < $j && $argv[$i + 1][0] !== '-')
                    {
                        $value = $argv[$i + 1];
                        $i++;
                    }
                    else
                    {
                        $value = isset($out[$key]) ? $out[$key] : true;
                    }
                    $out[$key] = $value;
                }
                // --bar=baz
                else {
                    $key = substr($arg, 2, $eqPos - 2);
                    $value = substr($arg, $eqPos + 1);
                    $out[$key] = $value;
                }
            }
            // -k=value -abc
            else if (substr($arg, 0, 1) == '-') {
                // -k=value
                if (substr($arg, 2, 1) == '=') {
                    $key = substr($arg, 1, 1);
                    $value = substr($arg, 3);
                    $out[$key] = $value;
                }
                // -abc
                else {
                    $chars = str_split(substr($arg, 1));
                    foreach ($chars as $char) {
                        $key = $char;
                        $value = isset($out[$key]) ? $out[$key] : true;
                        $out[$key] = $value;
                    }
                    // -a value1 -abc value2
                    if ($i + 1 < $j && $argv[$i + 1][0] !== '-')
                    {
                        $out[$key]      = $argv[$i + 1];
                        $i++;
                    }
                }
            }
            // plain-arg
            else {
                $value = $arg;
                $out[] = $value;
            }
        }

        if (isset($taskData['assign'])) {
            if (is_array($taskData['assign'])) {
                foreach ($taskData['assign'] as $k => $v) {
                    if (isset($out[$k])) {
                        $out[$v] = $out[$k];
                    }
                }
            } elseif (is_string($taskData['assign']) && isset($out[0])) {
                $out[$out['assign']] = $out[0];
            }
        }

        return array(
            'service' => $taskData['service'],
            'task' => $taskData['task'],
            'parameter' => $this->prepareParameters(
                isset($taskData['parameter']) ? (array)$taskData['parameter'] : array(),
                $out
            )
        );

        return $out;
    }

    /**
     *
     * @param string $task the key of the task to get
     * @return mixed the task array or a specifig task (if $task is set)
     */
    public function getTask($task = null)
    {
        $tasks = $this->tasks;
        if (!$task) {
            return $tasks;
        }
        return isset($tasks[$task]) ? $tasks[$task] : null;
    }

    /**
     * merges the given parameters with the tasks default parameters
     *
     * @param array $parameters the route parameters
     * @param array $values the given parameters from the task
     */
    public function prepareParameters($parameters, $values)
    {
//        foreach ($values as $key => $value) {
//            if (isset($parameters[$key])) {
//                $parameters[$key] = $value;
//            }
//        }
        return array_merge($parameters, $values);
    }

    /**
     *
     * @return \Pimple\Container
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

}
