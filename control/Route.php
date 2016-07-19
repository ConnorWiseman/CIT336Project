<?php


/**
 * Represents a single route to be added to a Router instance's collection of
 * routes. Routes support only a single method- though Router supports routes
 * with identical route names and differing HTTP methods.
 */
final class Route {


    /**
     * A callback function to execute when this route is dispatched.
     * @var Callable
     */
    private $_callback;


    /**
     * The HTTP method used by this route instance.
     * @var string
     */
    private $_method;


    /**
     * The route string represented by this route instance.
     * @var string
     */
    private $_route;


    /**
     * Initializes this route's HTTP method, route string, and callback
     * function.
     * @param string   $method
     * @param string   $route
     * @param Callable $callback
     */
    public function __construct($method, $route, Callable $callback) {
        $this->_method = strtoupper($method);
        $this->_route = $route;
        $this->_callback = $callback;
    }


    /**
     * Calls this route instance's associated callback function, passing in an
     * array of parameters, either $_GET or $_POST data, and a View instance
     * from the Router.
     * @param  Array  $params
     * @param  Array  $request
     * @param  View   $view
     * @throws Exception If the route instance's callback is undefined.
     */
    public function dispatch(Array $params, Array $request, View $view) {
        // If no callback is defined, throw an error.
        if (!isset($this->_callback)) {
            throw new Exception("Route {$this->_route} callback undefined.");
        }

        return call_user_func($this->_callback, $params, $request, $view);
    }


    /**
     * Returns this route's HTTP method.
     * @return string
     */
    public function getMethod() {
        return $this->_method;
    }


    /**
     * Returns a regular expression string for parsing placeholders found in
     * this route's route string. Placeholders in route strings are defined
     * similarly to placeholders in PDO prepared statements; they're always
     * prefixed by a colon.
     * @return string
     */
    public function getRegex() {
        return preg_replace('/(:\w+)/', '([\w-%]+)', $this->_route);
    }


    /**
     * Returns this route's route string.
     * @return string
     */
    public function getRoute() {
        return $this->_route;
    }
}