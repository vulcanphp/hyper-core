<?php

use hyper\application;
use hyper\database;
use hyper\helpers\vite;
use hyper\query;
use hyper\request;
use hyper\response;
use hyper\router;
use hyper\template;
use hyper\utils\cache;
use hyper\utils\collect;
use hyper\session;
use hyper\utils\sanitizer;
use hyper\utils\validator;

// Foundation Shortcut
function app(): application
{
    return application::$app;
}

function request(): request
{
    return application::$app->request;
}

function response(): response
{
    return application::$app->response;
}

function redirect(string $url, bool $replace = true, int $httpCode = 0)
{
    return application::$app->response->redirect($url, $replace, $httpCode);
}

function session(): session
{
    return application::$app->session;
}

function router(): router
{
    return application::$app->router;
}

function database(): database
{
    return application::$app->database;
}

function query(string $table): query
{
    return new query(database: application::$app->database, table: $table);
}

function template(string $template, array $context = []): response
{
    $engine = new template();
    return application::$app->response->write(
        $engine->render($template, $context)
    );
}

function url(string $path = ''): string
{
    return rtrim(application::$app->request->rootUrl . '/' . ltrim(str_replace(['\\'], ['/'], $path), '/'), '/');
}

function public_url(string $path = ''): string
{
    return url('public/' . ltrim($path, '/'));
}

function asset_url(string $path = ''): string
{
    $path = application::$app->env['asset_url'] . ltrim($path, '/');
    return strpos($path, '/', 0) === 0 ? url($path) : $path;
}

function media_url(string $path = ''): string
{
    $path = application::$app->env['media_url'] . ltrim($path, '/');
    return strpos($path, '/', 0) === 0 ? url($path) : $path;
}

function request_url(): string
{
    return application::$app->request->url;
}

function route_url(string $name, ?string $context = null): string
{
    return url(application::$app->router->route($name, $context));
}

function app_dir(string $path = '/'): string
{
    return rtrim(application::$app->path . '/' . ltrim($path), '/');
}

function root_dir(string $path = ''): string
{
    return rtrim(ROOT_DIR . '/' . ltrim($path, '/'), '/');
}

// Helper/Utils Shortcut

function dump(...$args)
{
    echo '<style>body{font-size:18px}</style><pre>';
    var_dump(...$args);
    echo '</pre>';
}

function dd(...$args)
{
    dump(...$args);
    die(0);
}

function env(string $key, $default = null): mixed
{
    return application::$app->env[$key] ??  $default;
}

function debugger(string $type, mixed $log): void
{
    application::$app->debugger->log($type, $log);
}

function csrf_token(): ?string
{
    return application::$app->session->get('_token');
}

function csrf(): string
{
    return sprintf('<input type="hidden" name="_token" value="%s" />', csrf_token());
}

function user(?string $key = null, $default = null): mixed
{
    return $key !== null ? (application::$app->request->user[$key] ?? $default) : application::$app->request->user;
}

function collect(array $items = []): collect
{
    return collect::make($items);
}

$caches = [];
function cache(string $name = 'default'): cache
{
    global $caches;

    if (!isset($caches[$name])) {
        $caches[$name] = new cache($name);
    }

    return $caches[$name];
}

function __(?string $text = '', bool $strict = false): ?string
{
    return application::$app->translator->translate($text, $strict);
}

function vite($config): vite
{
    return new vite($config);
}

function template_exists(string $template): bool
{
    return file_exists(app_dir('templates/' . str_replace('.php', '', $template) . '.php'));
}

function input(array $filter = []): sanitizer
{
    return new sanitizer(application::$app->request->all($filter));
}

function validator(array $rules, array $data): sanitizer
{
    $validator = new validator();
    $result = $validator->validate($rules, $data);

    if ($result) {
        return new sanitizer($result);
    }

    throw new Exception($validator->getFirstError() ?? 'validation failed');
}

function _e(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function fire_script(): string
{
    return file_get_contents(__DIR__ . '/../scripts/fire.js');
}
