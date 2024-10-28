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
 */
class application
{
    /** @var application Singleton instance of the application */
    public static application $app;

    /** @var debugger Debugger instance for logging and debugging purposes */
    public debugger $debugger;

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
     * @param string|null $routesPath Path to route definitions.
     * @param array $requirePath Paths to required files for application setup.
     */
    public function __construct(
        public string $path,
        public array $env = [],
        private array $providers = [],
        private array $middlewares = [],
        private ?string $routesPath = null,
        private array $requirePath = []
    ) {
        self::$app = $this;

        // Load environment configurations
        $this->env = array_merge($this->env, require $this->path . '/env.php');

        // Initialize debugger
        $this->debugger = new debugger($this->env['debug']);
        $this->debugger->log('app', 'creating application instance');

        // Load core components
        $this->session = new session();
        $this->request = new request();
        $this->response = new response();
        $this->router = new router(new middleware());
        $this->database = new database($this->env['database']);
        $this->translator = new translator($this->env['lang'], $this->env['lang_dir']);

        $this->debugger->log('app', 'application instance created');
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
            $this->debugger->log('app', "requiring file from: {$require}");
            require $require;
        }

        $this->debugger->log('app', 'running service providers, total: ' . count($this->providers));

        // Execute all registered service providers
        foreach ($this->providers as $provider) {
            call_user_func($provider, $this);
        }

        // Load route definitions if a route path is specified
        if ($this->routesPath !== null) {
            $this->debugger->log('app', "loading routes from: {$this->routesPath}");
            foreach (require $this->routesPath as $route) {
                $this->router->add(...$route);
            }
        }

        // Register all middleware for routing
        foreach ($this->middlewares as $middleware) {
            $this->router->getMiddleware()->add($middleware);
        }

        // Dispatch routing and handle the request
        $this->debugger->log('app', 'dispatching router');
        $response = $this->router->dispatch($this->request);

        // Save any updates from the translator (language changes)
        $this->translator->save();

        // Send the response to the client
        $response->send();
        $this->debugger->log('app', 'response sent to client');
    }
}
