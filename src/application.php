<?php

namespace hyper;

class application
{
    public static application $app;
    public debugger $debugger;
    public request $request;
    public response $response;
    public router $router;
    public session $session;
    public database $database;
    public translator $translator;

    public function __construct(
        public string $path,
        public array $env = [],
        private array $providers = [],
        private array $middlewares = [],
        private ?string $routesPath = null,
        private array $requirePath = []
    ) {
        self::$app = $this;

        // register debugger
        $this->debugger = new debugger($env['debug']);
        $this->debugger->log('app', 'creating application');

        // load application core classes
        $this->session = new session();
        $this->request = new request();
        $this->response = new response();
        $this->router = new router(new middleware());
        $this->database = new database($env['database']);
        $this->translator = new translator($env['lang'], $env['lang_dir']);

        $this->debugger->log('app', 'application created');
    }

    public function addServiceProvider(callable $provider): self
    {
        $this->providers[] = $provider;
        return $this;
    }

    public function addRouteMiddleware(callable $middleware): self
    {
        $this->middlewares[] = $middleware;
        return $this;
    }

    public function run(): void
    {
        // Require application files 
        foreach ($this->requirePath as $require) {
            $this->debugger->log('app', "require file from: {$require}");
            require $require;
        }

        $this->debugger->log('app', 'running providers, total (' . count($this->providers) . ')');

        // Run service providers
        foreach ($this->providers as $provider) {
            call_user_func($provider, $this);
        }

        if ($this->routesPath !== null) {
            $this->debugger->log('app', "loading routes from: {$this->routesPath}");
            foreach (require $this->routesPath as $route) {
                $this->router->add(...$route);
            }
        }

        // Add Router middleware
        foreach ($this->middlewares as $middleware) {
            $this->router->getMiddleware()->add($middleware);
        }

        // Dispatch the router
        $this->debugger->log('app', 'despatching router');

        // dispatch view response
        $response = $this->router->dispatch($this->request);

        // save translator changes
        $this->translator->save();

        // send response to client
        $response->send();

        $this->debugger->log('app', 'response sent');
    }
}
