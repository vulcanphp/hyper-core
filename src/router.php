<?php

namespace hyper;

use Exception;
use ReflectionFunction;
use ReflectionMethod;

/**
 * Class router
 * 
 * A basic router for handling HTTP requests, middleware, and dispatching routes to their respective handlers.
 * 
 * @package hyper
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 * @version 1.0.1
 */
class router
{
    /** @var array Stores all defined routes */
    private array $routes = [];

    /** @var middleware Handles middleware for the router */
    private middleware $middleware;

    /**
     * Router constructor.
     * 
     * @param middleware|null $middleware Optional middleware instance, or initializes a new one.
     */
    public function __construct(?middleware $middleware = null)
    {
        $this->middleware = $middleware ?? new middleware();
    }

    /**
     * Get the middleware instance.
     * 
     * @return middleware Returns the middleware instance.
     */
    public function getMiddleware(): middleware
    {
        return $this->middleware;
    }

    /**
     * Add a new route to the router.
     * 
     * @param string $path Route path.
     * @param string|array|null $method HTTP method(s) allowed for this route.
     * @param callable|string|array|null $callback The handler or callback for the route.
     * @param string|null $template Optional template for the route.
     * @param string|null $name Optional name for the route.
     * @param array $middleware Middleware specific to this route.
     * 
     * @return self Returns the router instance to allow method chaining.
     */
    public function add(
        string $path,
        string|array|null $method = null,
        callable|string|array|null $callback = null,
        string|null $template = null,
        string|null $name = null,
        array $middleware = []
    ): self {
        // Define the route properties
        $route = [
            'path' => $path,
            'method' => $method ?? 'GET',
            'callback' => $callback,
            'template' => $template,
            'middleware' => $middleware
        ];

        // Store the route by name if given, otherwise add to unnamed routes array
        if ($name !== null) {
            $this->routes[$name] = $route;
        } else {
            $this->routes[] = $route;
        }

        return $this;
    }

    /**
     * Get the URL path for a named route.
     * 
     * @param string $name The name of the route.
     * @param string|null $context Optional context parameter for dynamic segments.
     * 
     * @return string Returns the route's path.
     * 
     * @throws Exception if the route does not exist.
     */
    public function route(string $name, null|string|array $context = null): string
    {
        // Retrieve the route path by name or throw an exception
        $route = $this->routes[$name]['path'] ?? null;
        if ($route === null) {
            throw new Exception(sprintf('Route (%s) does not found.', $name));
        }

        // Replace dynamic parameters in route path with context, if provided
        if ($context !== null) {
            if (is_array($context)) {
                foreach ($context as $key => $value) {
                    $route = preg_replace('/\{' . $key . '\}/', $value, $route);
                }
            } else {
                $route = preg_replace('/\{[a-zA-Z]+\}/', $context, $route);
            }
        }

        return $route;
    }

    /**
     * Dispatches the request to the appropriate route callback.
     * 
     * @param request $request The current HTTP request.
     * 
     * @return response The HTTP response from the matched route.
     */
    public function dispatch(request $request, response $response): response
    {
        // Iterate through all routes to find a match
        foreach ($this->routes as $route) {
            if ($this->match($route['method'], $route['path'], $request)) {
                // Add route-specific middleware to the middleware stack
                foreach ($route['middleware'] as $middleware) {
                    $this->middleware->add($middleware);
                }

                // Execute middleware stack and return response if middleware stops request
                $middlewareResponse = $this->middleware->handle($request, $response);
                if ($middlewareResponse) {
                    return $middlewareResponse;
                }

                // Handle template rendering or instantiate a class for callback if specified
                if (isset($route['template'])) {
                    $route['callback'] = fn() => template($route['template']);
                } elseif (is_array($route['callback']) && is_string($route['callback'][0])) {
                    $route['callback'][0] = new $route['callback'][0];
                }

                // Call the matched route's callback
                return $this->callback($route['callback'], $request, $response);
            }
        }

        // Return a 404 response if no route was matched
        return new response('Not Found', 404);
    }

    /**
     * Match the request with a route.
     * 
     * @param string|array $routeMethod HTTP method(s) allowed for the route.
     * @param string $routePath Route path pattern.
     * @param request $request The current HTTP request.
     * 
     * @return bool True if the request matches the route, false otherwise.
     */
    private function match($routeMethod, $routePath, request $request): bool
    {
        // Check if the request method is allowed for this route
        if (!in_array($request->method, (array) $routeMethod)) {
            return false;
        }

        // Create route pattern with optional named parameters, Ex: /users/{id?}
        $pattern = preg_replace('/\/\{[a-zA-Z]+\?\}/', '(?:/([a-zA-Z0-9_-]*))?', $routePath);

        // Create route pattern with required named parameters, Ex: /users/{id}
        $pattern = preg_replace('/\{[a-zA-Z]+\}/', '([a-zA-Z0-9_-]+)', $pattern);

        // Create route pattern with optional wildcards, Ex: /users/*
        $pattern = str_replace(['/', '*'], ['\/', '(.*)'], $pattern);

        // Attempt to match the request path with the route pattern
        if (preg_match("/^$pattern\$/", $request->path, $matches)) {
            array_shift($matches);

            // Map matched segments to parameter names in the route path
            if (preg_match_all('/\{([^\}]+)\}/', $routePath, $names)) {
                if (count($names[1]) === count($matches)) {
                    // If the number of parameter names matches the number of segments, map the segments to the parameter names
                    $matches = array_combine(array_map(fn($name) => str_replace('?', '', $name), $names[1]), $matches);
                }
            }

            // Set router parameters into reqouest class and return as route matched.
            $request->params = $matches;
            return true;
        }

        // returns as route not matched.
        return false;
    }

    /**
     * Calls the route's callback function.
     * 
     * @param callable $callback The route's callback.
     * @param request $request The current HTTP request.
     * @param response $response The current HTTP response.
     * 
     * @return response The response generated by the callback.
     */
    private function callback(callable $callback, request $request, response $response): response
    {
        // Determine if the callback is a method, static method, or function
        if (is_array($callback)) {
            $reflection = new ReflectionMethod($callback[0], $callback[1]);
        } elseif (is_string($callback) && strpos($callback, '::') !== false) {
            $parts = explode('::', $callback);
            $reflection = new ReflectionMethod($parts[0], $parts[1]);
        } else {
            $reflection = new ReflectionFunction($callback);
        }

        // Prepare arguments to pass to the callback based on reflection
        $arguments = [];
        foreach ($reflection->getParameters() as $key => $parameter) {
            $name = $parameter->getName();
            if (in_array($name, ['request', 'response'], true)) {
                // Set arguments value from request and response
                $arguments[$name] = ['request' => $request, 'response' => $response][$name];
            } else {
                // Set arguments value from matched route parameters else set default value
                $arguments[$name] = $request->params[$name] ??
                    ($request->params[$key] ?? ($parameter->isDefaultValueAvailable() ? $parameter->getDefaultValue() : null));
            }
        }

        // Execute the callback with the prepared arguments
        return call_user_func($callback, ...$arguments);
    }
}
