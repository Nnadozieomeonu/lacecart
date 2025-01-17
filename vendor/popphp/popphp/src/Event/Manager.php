<?php
/**
 * Pop PHP Framework (http://www.popphp.org/)
 *
 * @link       https://github.com/popphp/popphp-framework
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2015 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 */

/**
 * @namespace
 */
namespace Pop\Event;

/**
 * Event manager class
 *
 * @category   Pop
 * @package    Pop_Event
 * @author     Nick Sagona, III <dev@nolainteractive.com>
 * @copyright  Copyright (c) 2009-2015 NOLA Interactive, LLC. (http://www.nolainteractive.com)
 * @license    http://www.popphp.org/license     New BSD License
 * @version    2.0.0
 */
class Manager
{

    /**
     * Constant to stop the event manager
     * @var string
     */
    const STOP = 'Pop\Event\Manager::STOP';

    /**
     * Constant to send a kill signal to the application
     * @var string
     */
    const KILL = 'Pop\Event\Manager::KILL';

    /**
     * Event listeners
     * @var array
     */
    protected $listeners = [];

    /**
     * Event results
     * @var array
     */
    protected $results = [];

    /**
     * Event 'alive' tracking flag
     * @var boolean
     */
    protected $alive = true;

    /**
     * Constructor
     *
     * Instantiate the event manager object.
     *
     * @param  string $name
     * @param  mixed  $action
     * @param  int    $priority
     * @return Manager
     */
    public function __construct($name = null, $action = null, $priority = 0)
    {
        if ((null !== $name) && (null !== $action)) {
            $this->on($name, $action, $priority);
        }
    }

    /**
     * Attach an event listener
     *
     *     $event->on('event.name', function() { ... });
     *     $event->on('event.name', new SomeClass());
     *     $event->on('event.name', [new SomeClass, 'foo']);
     *     $event->on('event.name', 'SomeClass');
     *     $event->on('event.name', 'SomeClass->foo');
     *     $event->on('event.name', 'SomeClass::bar');
     *
     * @param  string $name
     * @param  mixed  $action
     * @param  int    $priority
     * @return Manager
     */
    public function on($name, $action, $priority = 0)
    {
        if (!isset($this->listeners[$name])) {
            $this->listeners[$name] = new \SplPriorityQueue();
        }
        $this->listeners[$name]->insert($action, (int)$priority);

        return $this;
    }

    /**
     * Detach an event listener
     *
     * @param  string $name
     * @param  mixed  $action
     * @return Manager
     */
    public function off($name, $action)
    {
        // If the event exists, loop through and remove the action if found.
        if (isset($this->listeners[$name])) {
            $newListeners = new \SplPriorityQueue();

            $listeners = clone $this->listeners[$name];
            $listeners->setExtractFlags(\SplPriorityQueue::EXTR_BOTH);

            foreach ($listeners as $value) {
                $item = $listeners->current();
                if ($action !== $item['data']) {
                    $newListeners->insert($item['data'], $item['priority']);
                }
            }

            $this->listeners[$name] = $newListeners;
        }

        return $this;
    }

    /**
     * Return an event
     *
     * @param  string $name
     * @return mixed
     */
    public function get($name)
    {
        $listener = null;
        if (isset($this->listeners[$name])) {
            $listener = $this->listeners[$name];
        }

        return $listener;
    }

    /**
     * Determine whether the event manage has an event registered with it
     *
     * @param  string $name
     * @return boolean
     */
    public function has($name)
    {
        return (isset($this->listeners[$name]));
    }

    /**
     * Return the event results
     *
     * @param  string $name
     * @return mixed
     */
    public function getResults($name)
    {
        return (isset($this->results[$name]) ? $this->results[$name] : null);
    }

    /**
     * Determine if the project application is still alive or has been killed
     *
     * @return boolean
     */
    public function alive()
    {
        return $this->alive;
    }

    /**
     * Trigger an event listener priority
     *
     * @param  string $name
     * @param  array  $args
     * @throws Exception
     * @return void
     */
    public function trigger($name, array $args = [])
    {
        if (isset($this->listeners[$name])) {
            if (!isset($this->results[$name])) {
                $this->results[$name] = [];
            }

            foreach ($this->listeners[$name] as $action) {
                if (end($this->results[$name]) == self::STOP) {
                    return;
                }

                $args['result'] = end($this->results[$name]);
                $realArgs       = [];
                $params         = [];

                // Get and arrange the argument values in the correct order
                // If the action is a closure object
                if ($action instanceof \Closure) {
                    $refFunc = new \ReflectionFunction($action);
                    foreach ($refFunc->getParameters() as $key => $refParameter) {
                        $params[] = $refParameter->getName();
                    }
                // Else, if the action is a callable class/method combination
                } else if (is_string($action) || is_callable($action, false, $callable_name)) {
                    // If the callable action is a string, parse the class/method from it
                    if (is_string($action)) {
                        // If a static call
                        if (strpos($action, '::')) {
                            $ary  = explode('::', $action);
                            $cls  = $ary[0];
                            $mthd = $ary[1];
                        // If an instance call
                        } else if (strpos($action, '->')) {
                            $ary    = explode('->', $action);
                            $cls    = $ary[0];
                            $mthd   = $ary[1];
                            $action = [new $cls, $mthd];
                        // Else, if a new/construct call
                        } else {
                            $action = str_replace('new ', null, $action);
                            $cls  = $action;
                            $mthd = '__construct';
                        }
                    } else {
                        $cls  = substr($callable_name, 0, strpos($callable_name, ':'));
                        $mthd = substr($callable_name, (strrpos($callable_name, ':') + 1));
                    }

                    $methodExport = \ReflectionMethod::export($cls, $mthd, true);
                    // Get the method parameters
                    if (stripos($methodExport, 'Parameter') !== false) {
                        $matches = [];
                        preg_match_all('/Parameter \#(.*)\]/m', $methodExport, $matches);
                        if (isset($matches[0][0])) {
                            foreach ($matches[0] as $param) {
                                // Get name
                                $argName  = substr($param, strpos($param, '$'));
                                $argName  = trim(substr($argName, 0, strpos($argName, ' ')));
                                $params[] = str_replace('$', '', $argName);
                            }
                        }
                    }
                } else {
                    throw new Exception('Error: The action must be callable, i.e. a closure or class/method combination.');
                }

                foreach ($params as $value) {
                    $realArgs[$value] = $args[$value];
                }

                // If the method is the constructor, create object
                if (isset($mthd) && ($mthd == '__construct')) {
                    $reflect  = new \ReflectionClass($action);
                    $result   = $reflect->newInstanceArgs($realArgs);
                    $this->results[$name][] = $result;
                // Else, just call it
                } else {
                    $result = call_user_func_array($action, $realArgs);
                    $this->results[$name][] = $result;
                }

                if ($result == self::KILL) {
                    $this->alive = false;
                }
            }
        }
    }

}
