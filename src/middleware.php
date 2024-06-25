<?php

namespace hyper;

class middleware
{
    public function __construct(private array $middlewareStack = [])
    {
    }

    public function add(callable $middleware): self
    {
        $this->middlewareStack[] = $middleware;
        return $this;
    }

    public function handle(request $request): ?response
    {
        debugger('app', 'running middlewares, total (' . count($this->middlewareStack) . ')');
        foreach ($this->middlewareStack as $middleware) {
            $response = call_user_func($middleware, $request);
            if ($response instanceof response) {
                return $response;
            }
        }
        $this->middlewareStack = [];
        return null;
    }
}
