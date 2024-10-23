<?php

namespace hyper;

use Exception;
use ReflectionFunction;
use ReflectionMethod;

class router
{
    private array $routes = [];
    private middleware $middleware;

    public function __construct(?middleware $middleware = null)
    {
        $this->middleware = $middleware ?? new middleware();
    }

    public function getMiddleware(): middleware
    {
        return $this->middleware;
    }

    public function add(
        string $path,
        string|array|null $method = null,
        callable|string|array|null $callback = null,
        string|null $template = null,
        string|null $name = null,
        array $middleware = []
    ): self {
        $route = [
            'path' => $path,
            'method' => $method ?? 'GET',
            'callback' => $callback,
            'template' => $template,
            'middleware' => $middleware
        ];
        if ($name !== null) {
            $this->routes[$name] = $route;
        } else {
            $this->routes[] = $route;
        }
        return $this;
    }

    public function route(string $name, ?string $context = null): string
    {
        $route = $this->routes[$name]['path'] ?? null;
        if ($route === null) {
            throw new Exception(sprintf('Route (%s) does not found.', $name));
        }
        if ($context !== null) {
            $route = preg_replace('/\{[a-zA-Z]+\}/', $context, $route);
        }
        return $route;
    }

    public function dispatch(request $request): response
    {
        debugger('app', 'matching routes, total (' . count($this->routes) . ')');
        foreach ($this->routes as $route) {
            if ($this->match($route['method'], $route['path'], $request)) {
                debugger('app', "route matched: {$route['path']}");
                foreach ($route['middleware'] as $middleware) {
                    $this->middleware->add($middleware);
                }
                $middlewareResponse = $this->middleware->handle($request);
                if ($middlewareResponse) {
                    return $middlewareResponse;
                }
                if (isset($route['template'])) {
                    $route['callback'] = fn () => template($route['template']);
                } elseif (is_array($route['callback']) && is_string($route['callback'][0])) {
                    $route['callback'][0] = new $route['callback'][0];
                }
                debugger('app', 'dispatched route');
                return $this->callback($route['callback'], $request);
            }
        }
        debugger('app', "route not matched");
        return new response('Not Found', 404);
    }

    private function match($routeMethod, $routePath, request $request): bool
    {
        if (!in_array($request->method, (array)$routeMethod)) {
            return false;
        }
        $pattern = preg_replace('/\/\{[a-zA-Z]+\?\}/', '(?:/([a-zA-Z0-9_-]*))?', $routePath);
        $pattern = preg_replace('/\{[a-zA-Z]+\}/', '([a-zA-Z0-9_-]+)', $pattern);
        $pattern = str_replace(['/', '*'], ['\/', '(.*)'], $pattern);
        if (preg_match('/^' . $pattern . '$/', $request->path, $matches)) {
            array_shift($matches);
            if (preg_match_all('/\{([^\}]+)\}/', $routePath, $names)) {
                if (count($names[1]) === count($matches)) {
                    $matches = array_combine(array_map(fn ($name) => str_replace('?', '', $name), $names[1]), $matches);
                }
            }
            $request->params = $matches;
            return true;
        }
        return false;
    }

    private function callback(callable $callback, request $request): response
    {
        if (is_array($callback)) {
            $reflection = new ReflectionMethod($callback[0], $callback[1]);
        } elseif (is_string($callback) && strpos($callback, '::') !== false) {
            $parts = explode('::', $callback);
            $reflection = new ReflectionMethod($parts[0], $parts[1]);
        } else {
            $reflection = new ReflectionFunction($callback);
        }
        $arguments = [];
        foreach ($reflection->getParameters() as $key => $parameter) {
            $name = $parameter->getName();
            if (in_array($name, ['request', 'response'], true)) {
                $arguments[$name] = ['request' => $request, 'response' => application::$app->response][$name];
            } else {
                $arguments[$name] = $request->params[$name] ?? ($request->params[$key] ?? null);
            }
        }
        return call_user_func($callback, ...$arguments);
    }
}
