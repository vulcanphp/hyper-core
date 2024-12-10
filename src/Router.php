<?php

namespace Hyper;

use Exception;

/**
 * Class Router
 * 
 * A basic router for handling HTTP requests, middleware, and dispatching routes to their respective handlers.
 * 
 * @package hyper
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class Router
{
    /**
     * Construct a new router.
     *
     * @param array $routes An array of routes that should be added to the router.
     */
    public function __construct(private array $routes = [])
    {
    }

    /**
     * Add a GET route to the router.
     * 
     * @param string $path The path for the GET route.
     * @param callable|string|array $callback The handler or callback for the GET route.
     * 
     * @return self Returns the router instance to allow method chaining.
     */
    public function get(string $path, callable|string|array $callback): self
    {
        $this->add($path, 'GET', $callback);
        return $this;
    }

    /**
     * Add a POST route to the router.
     * 
     * @param string $path The path for the POST route.
     * @param callable|string|array $callback The handler or callback for the POST route.
     * 
     * @return self Returns the router instance to allow method chaining.
     */
    public function post(string $path, callable|string|array $callback): self
    {
        $this->add($path, 'POST', $callback);
        return $this;
    }

    /**
     * Add a PUT route to the router.
     * 
     * @param string $path The path for the PUT route.
     * @param callable|string|array $callback The handler or callback for the PUT route.
     * 
     * @return self Returns the router instance to allow method chaining.
     */
    public function put(string $path, callable|string|array $callback): self
    {
        $this->add($path, 'PUT', $callback);
        return $this;
    }

    /**
     * Add a PATCH route to the router.
     * 
     * @param string $path The path for the PATCH route.
     * @param callable|string|array $callback The handler or callback for the PATCH route.
     * 
     * @return self Returns the router instance to allow method chaining.
     */
    public function patch(string $path, callable|string|array $callback): self
    {
        $this->add($path, 'PATCH', $callback);
        return $this;
    }

    /**
     * Add a DELETE route to the router.
     * 
     * @param string $path The path for the DELETE route.
     * @param callable|string|array $callback The handler or callback for the DELETE route.
     * 
     * @return self Returns the router instance to allow method chaining.
     */
    public function delete(string $path, callable|string|array $callback): self
    {
        $this->add($path, 'DELETE', $callback);
        return $this;
    }

    /**
     * Add a route with a template to the router.
     * 
     * @param string $path The path for the route.
     * @param string $template The template to use for the route.
     * 
     * @return self Returns the router instance to allow method chaining.
     */
    public function template(string $path, string $template): self
    {
        $this->add(path: $path, template: $template);
        return $this;
    }

    /**
     * Assign middleware to the most recently added route.
     * 
     * @param string|array $middleware An array of middleware to be associated with the route.
     * 
     * @return self Returns the router instance to allow method chaining.
     */
    public function middleware(string|array $middleware): self
    {
        $this->routes[array_key_last($this->routes)]['middleware'] = $middleware;
        return $this;
    }

    /**
     * Assign a name to the most recently added route.
     * 
     * @param string $name The name to assign to the route.
     * 
     * @return self Returns the router instance to allow method chaining.
     */
    public function name(string $name): self
    {
        $key = array_key_last($this->routes);
        $this->routes[$name] = $this->routes[$key];
        unset($this->routes[$key]);
        return $this;
    }

    /**
     * Add a new route to the router.
     * 
     * @param string $path Route path.
     * @param string|array|null $method HTTP method(s) allowed for this route.
     * @param callable|string|array|null $callback The handler or callback for the route.
     * @param string|null $template Optional template for the route.
     * @param string|null $name Optional name for the route.
     * @param string|array $middleware Middleware specific to this route.
     * 
     * @return self Returns the router instance to allow method chaining.
     */
    public function add(
        string $path,
        string|array|null $method = null,
        callable|string|array|null $callback = null,
        string|null $template = null,
        string|null $name = null,
        string|array $middleware = []
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

        return rtrim($route, '*');
    }


    /**
     * Dispatches the incoming HTTP request to the appropriate route handler.
     * 
     * Iterates through the defined routes to find a match for the request path and method.
     * If a route matches, it queues any route-specific middleware and processes the middleware stack.
     * The response is returned if the middleware halts the request. Otherwise, it handles template
     * rendering or resolves the callback for the matched route, returning the callback's response.
     * If no route matches, a 404 'Not Found' response is returned.
     * 
     * @param Container $container The dependency injection container.
     * @param Middleware $middleware The middleware stack to be processed.
     * @param Request $request The HTTP request instance.
     * 
     * @return Response The HTTP response object.
     */
    public function dispatch(Container $container, Middleware $middleware, Request $request): Response
    {
        // Iterate through all routes to find a match
        foreach ($this->routes as $route) {
            if ($this->match($route['method'], $route['path'], $request)) {
                // Add route-specific middleware to the middleware stack
                $middleware->queue($route['middleware']);

                // Execute middleware stack and return response if middleware stops request
                $middlewareResponse = $middleware->process($container, $request);
                if ($middlewareResponse) {
                    return $middlewareResponse;
                }

                // Handle template rendering or instantiate a class for callback if specified
                if (isset($route['template'])) {
                    $route['callback'] = fn() => template($route['template']);
                }

                // Call the matched route's callback
                return $container->call($route['callback'], $request->getRouteParams());
            }
        }

        // Return a 404 response if no route was matched
        return new Response('Not Found', 404);
    }

    /**
     * Attempts to match the request path with the given route path and method.
     * 
     * @param string|array $routeMethod The HTTP method(s) allowed for this route.
     * @param string $routePath The route path to match against the request path.
     * @param Request $request The request object.
     * 
     * @return bool True if the route matches the request path and method, false otherwise.
     */
    private function match($routeMethod, $routePath, Request $request): bool
    {
        // Check if the request method is allowed for this route
        if (!in_array($request->getMethod(), (array) $routeMethod)) {
            return false;
        }

        // Create route pattern with optional named parameters, Ex: /users/{id?}
        $pattern = preg_replace('/\/\{[a-zA-Z]+\?\}/', '(?:/([a-zA-Z0-9_-]*))?', $routePath);

        // Create route pattern with required named parameters, Ex: /users/{id}
        $pattern = preg_replace('/\{[a-zA-Z]+\}/', '([a-zA-Z0-9_-]+)', $pattern);

        // Create route pattern with optional wildcards, Ex: /users/*
        $pattern = str_replace(['/', '*'], ['\/', '(.*)'], $pattern);

        // Attempt to match the request path with the route pattern
        if (preg_match("/^$pattern\$/", $request->getPath(), $matches)) {
            array_shift($matches);

            // Map matched segments to parameter names in the route path
            if (preg_match_all('/\{([^\}]+)\}/', $routePath, $names)) {
                if (count($names[1]) === count($matches)) {
                    // If the number of parameter names matches the number of segments,
                    // map the segments to the parameter names
                    $matches = array_combine(
                        array_map(
                            fn($name) => str_replace('?', '', $name),
                            $names[1]
                        ),
                        $matches
                    );
                }
            }

            // Set router parameters into reqouest class and return as route matched.
            $request->setRouteParams($matches);
            return true;
        }

        // returns as route not matched.
        return false;
    }
}