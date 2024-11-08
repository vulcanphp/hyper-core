<?php

namespace hyper;

/**
 * Class application
 * 
 * Main Application Class for Hyper PHP Framework.
 * Handles the setup, initialization, and execution of the application.
 * 
 * @package hyper
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 * @version 1.0.1
 */
class application
{
    /** @var application Singleton instance of the application */
    public static application $app;

    /** @var request Manages incoming HTTP requests */
    public request $request;

    /** @var response Manages outgoing HTTP responses */
    public response $response;

    /** @var router Manages application routing and middleware dispatch */
    public router $router;

    /** @var session Handles user sessions and session data */
    public session $session;

    /** @var database Manages database connections and operations */
    public database $database;

    /** @var translator Handles language translation and localization */
    public translator $translator;

    /**
     * Application constructor.
     * Initializes core application services, loads environment settings, and prepares routing and middlewares.
     *
     * @param string $path Path to the application root.
     * @param array $env Application environment settings.
     * @param array $providers Service providers for additional functionalities.
     * @param array $middlewares Middleware functions to process requests.
     * @param string $routesPath Path to route definitions.
     * @param array $requirePath Paths to required files for application setup.
     */
    public function __construct(
        public string $path,
        private string $routesPath,
        public array $env = [],
        private array $providers = [],
        private array $middlewares = [],
        private array $requirePath = []
    ) {
        // Set the application instance statically.
        self::$app = $this;

        // Load core components
        $this->session = new session();
        $this->request = new request();
        $this->response = new response();
        $this->router = new router(new middleware());
        $this->database = new database($this->env['database']);
    }

    /**
     * Registers a new service provider.
     *
     * @param callable $provider A callable to provide additional services.
     * @return $this
     */
    public function addServiceProvider(callable $provider): self
    {
        $this->providers[] = $provider;
        return $this;
    }

    /**
     * Adds a new route middleware.
     *
     * @param callable $middleware A callable to process middleware for routes.
     * @return $this
     */
    public function addRouteMiddleware(callable $middleware): self
    {
        $this->middlewares[] = $middleware;
        return $this;
    }

    /**
     * Runs the application by initializing all service providers, loading routes, 
     * executing middleware, dispatching routes, and sending the response.
     */
    public function run(): void
    {
        // Load required application files
        foreach ($this->requirePath as $require) {
            require $require;
        }

        // Execute all registered service providers
        foreach ($this->providers as $provider) {
            call_user_func($provider, $this);
        }

        // Load route definitions if a route path is specified
        foreach (require $this->routesPath as $route) {
            $this->router->add(...$route);
        }

        // Register all middleware for routing
        foreach ($this->middlewares as $middleware) {
            $this->router->getMiddleware()->add($middleware);
        }

        // Dispatch routing and send the response
        $this->router->dispatch($this->request, $this->response)
            ->send();
    }
}
