<?php


// Require dependencies.
require_once('Route.php');


/**
 * A class for managing GET and POST requests to the application. Makes setting
 * up controllers a breeze.
 *
 * Designed to work with WordPress-style "pretty" URLs, but will also function
 * predictably with "ugly" URLs as long as they meet the following specific
 * pattern(as this project demonstration shows):
 *
 * example.com/index.php?action=view[&param1=value1...]
 *
 * In Nginx the configuration to make it work is something like this:
 * try_files $uri $uri/ /index.php?q=$uri&$args;
 *
 * In Apache it's... on second thought, just look it up. I don't use Apache
 * often enough to hazard a guess at what it is. Shouldn't be hard to find,
 * though, since it's the same pattern WordPress uses.
 *
 * This was the only part of my project that I couldn't manage entirely on my
 * own. My attempt worked, but... it didn't handle parameters in route names
 * very well, if at all. In the end I scrapped my code and started from scratch.
 * I drew a ton of inspiration from the following projects. Hope it's okay
 * that I looked outside my own knowledge for inspiration on my final project!
 * @link https://github.com/dannyvankooten/PHP-Router
 * @link https://github.com/noahbuscher/Macaw
 * @link https://github.com/usmanhalalit/140-chars-router
 * @link http://upshots.org/php/php-seriously-simple-router
 * @link http://upshots.org/php/php-regexrouter
 */
final class Router {


    /**
     * A collection of named routes.
     * @var array
     */
    private $_namedRoutes = Array();


    /**
     * A collection of routes.
     * @var array
     */
    private $_routes = Array();


    /**
     * Adds a route to the routes and namedRoutes arrays. Called by get and
     * post methods.
     * @param  string   $method
     * @param  string   $route
     * @param  Callable $callback
     * @return $this
     */
    private function _addRoute($method, $route, Callable $callback) {
        // Uppercase the method for consistency.
        $method = strtoupper($method);

        // Create a new Route and add it to the array of routes.
        $instance = new Route($method, $route, $callback);
        array_push($this->_routes, $instance);
        $this->_namedRoutes[$route] = $instance;

        return $this;
    }


    /**
     * Returns true if the specified key exists and is the first key in the
     * specified array. Called by handleCurrentRequest method.
     * @param  string  $key
     * @param  array   $array
     * @return boolean
     */
    private function _isFirstKey($key, $array) {
        $arrayKeys = array_keys($array);

        return (
            array_key_exists($key, $array) &&
            $array[$key] === $array[$arrayKeys[0]]
        );
    }


    /**
     * Sets an error callback to use if none of the routes in the router
     * instance manage to catch an incoming request.
     * @param  Callable $callback
     * @return $this
     */
    public function error(Callable $callback) {
        $instance = new Route('GET', null, $callback);
        $this->_namedRoutes['error'] = $instance;
        return $this;
    }


    /**
     * Adds a GET route to the route collection array.
     * @param  string   $route
     * @param  Callable $callback
     * @return $this
     */
    public function get($route, Callable $callback) {
        return $this->_addRoute('GET', $route, $callback);
    }


    /**
     * Adds a POST route to the route collection array.
     * @param  string   $route
     * @param  Callable $callback
     * @return $this
     */
    public function post($route, Callable $callback) {
        return $this->_addRoute('POST', $route, $callback);
    }


    /**
     * Handles a currently incoming request. Intended to be the last method
     * called by the application's main controller.
     * @param  Array  $get  $_GET data.
     * @param  Array  $post $_POST data.
     * @param  View   $view The view instance to pass to the dispatched route.
     */
    public function handleCurrentRequest(Array $get, Array $post, View $view) {
        // Detect which method the current request is using. Note that PUT and
        // DELETE are not supported methods! This isn't a complete framework,
        // it's just some scaffolding to use for my CIT336 final project.
        $method = getenv('REQUEST_METHOD');

        // Normalize the different request URL schemas we might be working with.
        // The result of both following URL schemas should be the same:
        // example.com/test/1
        // example.com?action=test&param=1
        if ($this->_isFirstKey('action', $get)) {
            $requestUrl = '/' . rtrim(implode('/', $get), '/');
        }
        else {
            $path = explode('/', getenv('SCRIPT_NAME'));
            array_pop($path);
            $path = implode('/', $path);
            $requestUrl = rtrim(getenv('REQUEST_URI'), '/');
            $requestUrl = str_replace($path, '', $requestUrl);
        }

        // Create a new array to hold any potential parameters.
        $params = array();

        // Iterate over each route object.
        foreach ($this->_routes as $route) {
            // Get the route's method.
            $routeMethod = $route->getMethod();

            // If this route doesn't have the appropriate method, skip the rest
            // of the loop.
            if ($method !== $routeMethod) {
                continue;
            }

            // Create a regex from the route, stripping any forward slashes off
            // the end. This regex will be used to generate another regex.
            $regex = rtrim($route->getRegex(), '/');

            // Create another regex matching the overall route string. This
            // regex will be used to parse URL parameters.
            $pattern = "@^{$regex}/?$@i";

            // If the current request does not match this particular route's
            // pattern, skip the rest of the loop.
            if (!preg_match($pattern, $requestUrl, $matches)) {
                continue;
            }

            // Remove matched text from the array of regex matches. We won't
            // use it, but if it's allowed to remain, it will be in the way.
            $matchedText = array_shift($matches);

            // Acquire all parameter keys in this route.
            if (preg_match_all(
                "/:([\w-%]+)/",
                $route->getRoute(),
                $param_keys)
            ) {
                // We only need the matches, so ignore the first element in the
                // resulting array.
                $param_keys = $param_keys[1];

                // If the number of parameter keys isn't the same as the number
                // of matching elements from the route's regex pattern, skip
                // the rest of the loop.
                if(count($param_keys) !== count($matches)) {
                    continue;
                }

                // Iterate over each of the parameter keys and add any matching
                // pair to the array of outgoing parameters.
                foreach ($param_keys as $key => $name) {
                    if (isset($matches[$key])) {
                        // Filter any found match, just in case.
                        $filtered = filter_var(
                            $matches[$key],
                            FILTER_SANITIZE_FULL_SPECIAL_CHARS
                        );

                        // Add the filtered match to the array of parameters.
                        $params[$name] = $filtered;
                    }
                }
            }

            // The data associated with this route's method will be passed to
            // its dispatch function, and for that we need it to be lowercase.
            $routeMethod = strtolower($routeMethod);

            // Dispatch this route.
            return $route->dispatch($params, ${$routeMethod}, $view);
        }

        // If no error handling route has been defined, create a default one.
        if (!array_key_exists('error', $this->_namedRoutes)) {
            $this->error(function($params, $request, $view) use ($requestUrl) {
                $view->render('error', Array(
                    'title' => 'Error',
                    'errorMessage' => "No route for {$requestUrl} has been defined."
                ));
            });
        }

        // This would be an ideal place to implement some kind of error logging
        // feature, but I didn't have the time.

        // Dispatch the error handling route if no other matching route can
        // be found.
        return $this->_namedRoutes['error']->dispatch($params, $get, $view);
    }
}