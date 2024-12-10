<?php

namespace Hyper;

use Exception;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;
use ReflectionMethod;

/**
 * Class Container
 *
 * Simple IoC container for resolving dependencies.
 *
 * @package hyper
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class Container
{
    /**
     * Registered bindings.
     *
     * @var array<string, callable|string|null>
     */
    private array $bindings = [];

    /**
     * Registered service providers.
     *
     * @var array<string, callable>
     */
    private array $providers = [];

    /**
     * Registered instances.
     *
     * @var array<string, mixed>
     */
    private array $instances = [];

    /**
     * Registered aliases.
     *
     * @var array<string, string>
     */
    private array $aliases = [];

    /**
     * A cache of reflection classes used for resolving bindings.
     *
     * @var array<string, ReflectionClass>
     */
    private array $reflectionCache = [];

    /**
     * Bind a class or interface to a concrete implementation.
     *
     * The concrete implementation can be a class, a closure, or an interface.
     * If no concrete implementation is given, the abstract name will be used.
     *
     * @param string $abstract The abstract name of the class or interface.
     * @param callable|string|null $concrete The concrete implementation.
     */
    public function bind(string $abstract, callable|string|null $concrete = null): void
    {
        $this->bindings[$abstract] = $concrete ?? $abstract;
    }

    /**
     * Register a binding as a singleton.
     *
     * A singleton binding returns the same instance each time it is requested.
     * The concrete implementation can be a class, a closure, or an interface.
     * If no concrete implementation is given, the abstract name will be used.
     *
     * @param string $abstract The abstract name of the class or interface.
     * @param callable|string|null $concrete The concrete implementation.
     */
    public function singleton(string $abstract, callable|string|null $concrete = null): void
    {
        $this->bind($abstract, $concrete);
        $this->instances[$abstract] = null; // Marks it for singleton resolution
    }

    /**
     * Register an alias for a given abstract name.
     *
     * @param string $alias The alias name.
     * @param string $abstract The abstract name.
     */
    public function alias(string $alias, string $abstract): void
    {
        $this->aliases[$alias] = $abstract;
    }

    /**
     * Registers a service provider.
     *
     * The service provider will register its bindings, and will be tracked
     * in the container.
     *
     * @param object $provider The service provider to register.
     */
    public function addServiceProvider($provider): void
    {
        $provider->register($this); // Register bindings
        $this->providers[] = $provider; // Track the provider
    }

    /**
     * Resolve a binding.
     *
     * If the binding is a singleton, returns the same instance each time it is
     * requested. If the binding is not a singleton, a new instance is created
     * each time it is requested.
     *
     * @param string $abstract The abstract name of the class or interface.
     *
     * @return mixed The resolved instance.
     */
    public function get(string $abstract): mixed
    {
        // Resolve aliases
        $abstract = $this->resolveAlias($abstract);

        // Return a singleton instance if it exists
        if (array_key_exists($abstract, $this->instances)) {
            if ($this->instances[$abstract] === null) {
                $this->instances[$abstract] = $this->build($this->bindings[$abstract]);
            }
            return $this->instances[$abstract];
        }

        // If not a singleton, resolve dynamically
        if (!isset($this->bindings[$abstract])) {
            return $this->build($abstract);
        }

        return $this->build($this->bindings[$abstract]);
    }

    /**
     * Calls a class method or a closure with the given parameters.
     *
     * This method supports three formats to call a class method or a closure.
     *
     * 1. Class name and method name as a string with '@' separator.
     *    Example: 'ClassName@methodName'.
     * 2. Class name and method name as an array.
     *    Example: ['ClassName', 'methodName'].
     * 3. A closure or callable.
     *
     * The method resolves the instance of the class if it is not given, and
     * resolves the method parameters by using the given parameters and the
     * container if the parameter is not given.
     *
     * @param array|string|callable $abstract The class name, method name or a closure.
     * @param array $parameters The parameters to pass to the method or closure.
     *
     * @throws Exception If the class does not exist, the method does not exist, or
     *                   the method parameters cannot be resolved.
     *
     * @return mixed The result of calling the method or closure.
     */
    public function call(array|string|callable $abstract, array $parameters = [])
    {
        if (is_callable($abstract)) {
            // If it's a closure or callable, just call it with parameters
            return $abstract(...$parameters);
        }

        // Split class and method from the "ClassName@methodName" format
        if (is_string($abstract) && str_contains($abstract, '@')) {
            [$class, $method] = explode('@', $abstract, 2);
        } elseif (is_array($abstract)) {
            [$class, $method] = $abstract;
        } else {
            throw new Exception("Invalid call format. Expected 'ClassName@methodName' or '[ClassName, methodName]'.");
        }

        // Resolve the class instance
        $instance = $this->get($class);

        // Check if the method exists
        if (!method_exists($instance, $method)) {
            throw new Exception("Method {$method} does not exist in class {$class}.");
        }

        // Use reflection to resolve method parameters
        $reflectionMethod = new ReflectionMethod($instance, $method);

        // Resolve method parameters
        $dependencies = array_map(
            fn($param) => $parameters[$param->getName()] ?? $this->resolveParameter($param),
            $reflectionMethod->getParameters()
        );

        // Call the method with resolved parameters
        return $reflectionMethod->invokeArgs($instance, $dependencies);
    }

    /**
     * Forget a binding.
     *
     * Removes the binding and its associated instances from the container.
     *
     * @param string $abstract The abstract name of the class or interface to forget.
     */
    public function forget(string $abstract): void
    {
        // Resolve alias to the actual abstract name
        $abstract = $this->aliases[$abstract] ?? $abstract;

        // Remove from instances, bindings, and aliases
        unset($this->instances[$abstract]);
        unset($this->bindings[$abstract]);

        // Remove associated aliases
        foreach ($this->aliases as $alias => $resolved) {
            if ($resolved === $abstract) {
                unset($this->aliases[$alias]);
            }
        }
    }

    /**
     * Resolve an alias to its concrete binding.
     *
     * @param string $abstract The abstract name of the class or interface.
     * @param array $seen An array of aliases that have been seen during resolution.
     *
     * @throws Exception If a circular alias is detected.
     *
     * @return string The concrete class name.
     */
    private function resolveAlias(string $abstract, array $seen = []): string
    {
        if (isset($this->aliases[$abstract])) {
            if (in_array($abstract, $seen, true)) {
                throw new Exception("Circular alias detected for {$abstract}.");
            }

            return $this->resolveAlias($this->aliases[$abstract], [...$seen, $abstract]);
        }

        return $abstract;
    }

    /**
     * Check if a binding exists.
     *
     * @param string $abstract The abstract name of the class or interface.
     *
     * @return bool True if the binding exists, false otherwise.
     */
    public function has(string $abstract): bool
    {
        return isset($this->bindings[$abstract]) || isset($this->aliases[$abstract]) || class_exists($abstract);
    }

    /**
     * Boot all registered service providers.
     *
     * This method will call the `boot` method of each provider, which
     * is typically used to register service container bindings or
     * perform other setup tasks.
     *
     * @return void
     */
    public function bootServiceProviders(): void
    {
        foreach ($this->providers as $provider) {
            if (method_exists($provider, 'boot')) {
                $provider->boot($this); // Call the boot method
            }
        }
    }

    /**
     * Retrieve the current state of the container.
     *
     * This method returns an array containing the current bindings, instances,
     * aliases, registered providers, and deferred providers within the container.
     *
     * @return array An associative array representing the container's internal state.
     */
    public function debug(): array
    {
        return [
            'bindings' => $this->bindings,
            'instances' => array_filter($this->instances, fn($instance) => $instance !== null),
            'aliases' => $this->aliases,
            'providers' => array_map(fn($provider) => get_class($provider), $this->providers),
        ];
    }

    /**
     * Clear all of the registered bindings, instances, reflection cache,
     * providers and aliases from the container.
     *
     * @return void
     */
    public function flush()
    {
        $this->bindings = [];
        $this->instances = [];
        $this->reflectionCache = [];
        $this->providers = [];
        $this->aliases = [];
    }

    /**
     * Build an instance of the given concrete class or closure.
     *
     * This method attempts to create an instance of the specified class or invoke
     * the provided closure. If the concrete is a callable, it is executed with
     * the container as its parameter. If it is a class name, the method uses
     * reflection to resolve and instantiate the class with its dependencies.
     *
     * @param string|callable $concrete The class name or closure to build.
     * 
     * @return mixed The constructed instance.
     *
     * @throws Exception If the class does not exist or is not instantiable.
     */
    private function build(string|callable $concrete): mixed
    {
        if (is_callable($concrete)) {
            return $concrete($this);
        }

        if (!isset($this->reflectionCache[$concrete])) {
            if (!class_exists($concrete)) {
                throw new Exception("Class {$concrete} does not exist.");
            }

            // Cache the reflection class
            $this->reflectionCache[$concrete] = new ReflectionClass($concrete);
        }

        $reflection = $this->reflectionCache[$concrete];

        if (!$reflection->isInstantiable()) {
            throw new Exception("Class {$concrete} is not instantiable.");
        }

        $constructor = $reflection->getConstructor();

        if (!$constructor) {
            return $reflection->newInstance();
        }

        $dependencies = array_map(
            fn($param) => $this->resolveParameter($param),
            $constructor->getParameters()
        );

        return $reflection->newInstanceArgs($dependencies);
    }

    /**
     * Resolve a parameter by type.
     *
     * Given a parameter, attempt to resolve its value. If the type is not a
     * built-in type, assume it is a class name and attempt to resolve it from
     * the container. If the parameter has a default value, return that.
     *
     * @param ReflectionParameter $param The parameter to resolve.
     *
     * @return mixed The resolved value.
     *
     * @throws Exception If the parameter could not be resolved.
     */
    private function resolveParameter(ReflectionParameter $param): mixed
    {
        $type = $param->getType();

        if (!$type) {
            throw new Exception("Unable to resolve parameter {$param->getName()}.");
        }

        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            return $this->get($type->getName());
        }

        if ($type instanceof ReflectionUnionType) {
            // Handle union types (e.g., int|string)
            foreach ($type->getTypes() as $unionType) {
                if ($unionType instanceof ReflectionNamedType && !$unionType->isBuiltin()) {
                    return $this->get($unionType->getName());
                }
            }
        }

        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }

        throw new Exception("Unable to resolve parameter {$param->getName()}.");
    }
}