<?php

namespace hyper;

/**
 * Class middleware
 * 
 * Base class for managing middleware in a stack-based approach. This allows
 * chaining multiple middleware components to process an incoming request.
 * 
 * @package hyper
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class middleware
{
    /**
     * middleware constructor.
     * Initializes the middleware stack.
     *
     * @param array $middlewareStack Optional array of middleware callables.
     */
    public function __construct(private array $middlewareStack = [])
    {
    }

    /**
     * Adds a middleware callable to the stack.
     * Each middleware should be a callable that takes a `request` object as its parameter.
     *
     * @param callable $middleware The middleware callable to add to the stack.
     * @return $this Current middleware instance for method chaining.
     */
    public function add(callable $middleware): self
    {
        $this->middlewareStack[] = $middleware;
        return $this;
    }

    /**
     * Processes the request through the middleware stack.
     * Iterates through each middleware in the stack, passing the `request` object to it.
     * If a middleware returns a `response` instance, the middleware chain is interrupted,
     * and the response is immediately returned.
     *
     * @param request $request The request object to pass through the middleware stack.
     * @param response $response The response object to pass through the middleware stack.
     * @return response|null The first response instance returned by middleware, or null if none returned a response.
     */
    public function handle(request $request, response $response): ?response
    {
        foreach ($this->middlewareStack as $middleware) {
            // Execute the middleware with the request
            $resp = call_user_func($middleware, $request, $response);

            // If a response is returned, stop processing and return it
            if ($resp instanceof response) {
                return $resp;
            }
        }

        // Clear middleware stack after execution
        $this->middlewareStack = [];

        // Return null if no middleware generated a response
        return null;
    }
}
