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
use hyper\utils\settings;
use hyper\utils\validator;

/**
 * Get the current application instance.
 *
 * @return \hyper\application
 */
function app(): application
{
    return application::$app;
}

/**
 * Get the current request instance.
 *
 * @return \hyper\request
 */
function request(): request
{
    return application::$app->request;
}

/**
 * Get the current response instance.
 *
 * @return \hyper\response
 */
function response(): response
{
    return application::$app->response;
}

/**
 * Redirect to a specified URL.
 *
 * @param string $url The URL to redirect to.
 * @param bool $replace Whether to replace the current header. Default is true.
 * @param int $httpCode The HTTP status code for the redirection. Default is 0.
 */
function redirect(string $url, bool $replace = true, int $httpCode = 0): void
{
    application::$app->response->redirect($url, $replace, $httpCode);
}

/**
 * Get the current session instance.
 *
 * @return \hyper\session
 */
function session(): session
{
    return application::$app->session;
}

/**
 * Get the current router instance.
 *
 * @return \hyper\router
 */
function router(): router
{
    return application::$app->router;
}


/**
 * Get the current database instance.
 *
 * @return \hyper\database The database instance.
 */
function database(): database
{
    return application::$app->database;
}

/**
 * Create a new query instance.
 *
 * @param string $table The name of the table.
 *
 * @return \hyper\query The query instance.
 */
function query(string $table): query
{
    return new query(database: application::$app->database, table: $table);
}

/**
 * Render a template and return the response.
 *
 * @param string $template
 * @param array $context
 * @return \hyper\response
 */
function template(string $template, array $context = []): response
{
    $engine = new template();
    return application::$app->response->write(
        $engine->render($template, $context)
    );
}

/**
 * Check if a template exists.
 *
 * @param string $template The name of the template file, without the .php extension.
 *
 * @return bool True if the template exists, false otherwise.
 */
function template_exists(string $template): bool
{
    return file_exists(
        app_dir('templates/' . str_replace('.php', '', $template) . '.php')
    );
}

/**
 * Generate a URL from a given path.
 *
 * The path can be relative or absolute. If it is relative, it will be
 * resolved relative to the root URL of the application. If it is absolute,
 * it will be returned verbatim.
 *
 * @param string $path The path to generate a URL for.
 *
 * @return string The generated URL.
 */
function url(string $path = ''): string
{
    return rtrim(application::$app->request->rootUrl . '/' . ltrim(str_replace(['\\'], ['/'], $path), '/'), '/');
}

/**
 * Generate a URL from a given path relative to the public directory.
 *
 * The path can be relative or absolute. If it is relative, it will be
 * resolved relative to the public directory. If it is absolute,
 * it will be returned verbatim.
 *
 * @param string $path The path to generate a URL for.
 *
 * @return string The generated URL.
 */
function public_url(string $path = ''): string
{
    return url('public/' . ltrim($path, '/'));
}

/**
 * Generate a URL from a given path relative to the asset directory.
 *
 * The path can be relative or absolute. If it is relative, it will be
 * resolved relative to the asset directory. If it is absolute,
 * it will be returned verbatim.
 *
 * @param string $path The path to generate a URL for.
 *
 * @return string The generated URL.
 */
function asset_url(string $path = ''): string
{
    $path = application::$app->env['asset_url'] . ltrim($path, '/');
    return strpos($path, '/', 0) === 0 ? url($path) : $path;
}

/**
 * Generate a URL from a given path relative to the media directory.
 *
 * The path can be relative or absolute. If it is relative, it will be
 * resolved relative to the media directory. If it is absolute,
 * it will be returned verbatim.
 *
 * @param string $path The path to generate a URL for.
 *
 * @return string The generated URL.
 */
function media_url(string $path = ''): string
{
    $path = application::$app->env['media_url'] . ltrim($path, '/');
    return strpos($path, '/', 0) === 0 ? url($path) : $path;
}

/**
 * Get the URL of the current request.
 *
 * @return string The URL of the current request.
 */
function request_url(): string
{
    return application::$app->request->url;
}

/**
 * Generate a URL for a named route with an optional context.
 *
 * This function constructs a URL for a given named route, optionally
 * including additional context. The route name is resolved using the
 * application's router.
 *
 * @param string $name The name of the route to generate a URL for.
 * @param null|string|array $context Optional context to include in the route.
 *
 * @return string The generated URL for the specified route.
 */
function route_url(string $name, null|string|array $context = null): string
{
    return url(application::$app->router->route($name, $context));
}

/**
 * Get the application directory path with an optional appended path.
 *
 * This function returns the application's root directory path, optionally
 * appending a specified sub-path to it. The resulting path is normalized
 * with a single trailing slash.
 *
 * @param string $path The sub-path to append to the application directory path. Default is '/'.
 *
 * @return string The full path to the application directory, including the appended sub-path.
 */
function app_dir(string $path = '/'): string
{
    return rtrim(application::$app->path . '/' . ltrim($path), '/');
}

/**
 * Get the root directory path with an optional appended path.
 *
 * This function returns the root directory path, optionally appending a
 * specified sub-path to it. The resulting path is normalized with a single
 * trailing slash.
 *
 * @param string $path The sub-path to append to the root directory path. Default is '/'.
 *
 * @return string The full path to the root directory, including the appended sub-path.
 */
function root_dir(string $path = ''): string
{
    return rtrim(ROOT_DIR . '/' . ltrim($path, '/'), '/');
}

// Helper/Utils Shortcut

/**
 * Dump the given variable(s) with syntax highlighting.
 *
 * This function is a simple wrapper around the built-in {@see var_dump()} function,
 * which prints the given variable(s) as a string. The main difference is that
 * this function also adds a bit of CSS to increase the font size of the output.
 *
 * @param mixed ...$args The variable(s) to dump.
 *
 * @return void
 */
function dump(...$args)
{
    echo '<style>body{font-size:18px}</style><pre>';
    var_dump(...$args);
    echo '</pre>';
}

/**
 * Dump the given variable(s) with syntax highlighting and halt execution.
 *
 * This function is a simple wrapper around the {@see dump()} function, which
 * prints the given variable(s) as a string. The main difference is that this
 * function also halts execution after printing the dump.
 *
 * @param mixed ...$args The variable(s) to dump.
 *
 * @return void
 */
function dd(...$args)
{
    dump(...$args);
    die(0);
}

/**
 * Get the value of the specified environment variable.
 *
 * This function returns the value of the specified environment variable. If
 * the variable is not set, the given default value is returned instead.
 *
 * @param string $key The name of the environment variable to retrieve.
 * @param mixed $default The default value to return if the variable is not set.
 *
 * @return mixed The value of the specified environment variable, or the default
 * value if it is not set.
 */
function env(string $key, $default = null): mixed
{
    return application::$app->env[$key] ?? $default;
}

/**
 * Get the CSRF token.
 *
 * This function returns the CSRF token as a string. The CSRF token is a
 * random string that is generated when the application is booted. The CSRF
 * token is used to protect against cross-site request forgery attacks.
 *
 * @return string|null The CSRF token, or null if no token has been generated yet.
 */
function csrf_token(): ?string
{
    // Generate a new token if it doesn't exist
    if (!application::$app->session->has('_token')) {
        application::$app->session->set('_token', bin2hex(random_bytes(32)));
    }

    // Return the token
    return application::$app->session->get('_token');
}

/**
 * Generates a hidden form field containing the CSRF token.
 *
 * This function returns a string containing an HTML input field with the name
 * "_token" and the value of the CSRF token. The CSRF token is a random string
 * that is generated when the application is booted. It is used to protect
 * against cross-site request forgery attacks.
 *
 * @return string A string containing the CSRF token as a hidden form field.
 */
function csrf(): string
{
    return sprintf('<input type="hidden" name="_token" value="%s" />', csrf_token());
}

/**
 * Determine if the current request is made by a guest user.
 *
 * This function checks if the user is not set in the current application request,
 * indicating that the request is made by a guest (unauthenticated) user.
 *
 * @return bool True if the request is made by a guest user, false otherwise.
 */
function is_quest(): bool
{
    return !isset(application::$app->request->user);
}

/**
 * Get the currently authenticated user.
 *
 * If no user is authenticated, this function returns null. If a key is provided,
 * this function will return the value of the provided key from the user's data.
 * If the key does not exist in the user's data, the default value will be returned
 * instead.
 *
 * @param string $key The key to retrieve from the user's data.
 * @param mixed $default The default value to return if the key does not exist.
 *
 * @return mixed The user object, or the value of the provided key from the user's data.
 */
function user(?string $key = null, $default = null): mixed
{
    $user = application::$app->request->user;
    if ($key !== null) {
        return is_array($user) ? ($user[$key] ?? $default) : ($user->{$key} ?? $default);
    }

    return $user;
}

/**
 * Create a new collection instance.
 *
 * This function initializes a new collection object containing the given items.
 * The collection can be used to manipulate and interact with the array of items
 * using various collection methods.
 *
 * @param array $items The array of items to include in the collection.
 *
 * @return collect A collection instance containing the provided items.
 */
function collect(array $items = []): collect
{
    return collect::make($items);
}

/**
 * Retrieve or create a cache instance by name.
 *
 * This function returns an existing cache instance by the given name,
 * or creates a new one if it doesn't already exist. Cache instances
 * are stored globally and can be accessed using their names.
 *
 * @param string $name The name of the cache instance to retrieve or create. Default is 'default'.
 * @return cache The cache instance associated with the specified name.
 */
function cache(string $name = 'default'): cache
{
    global $caches;

    if (!isset($caches[$name])) {
        $caches[$name] = new cache($name);
    }

    return $caches[$name];
}


/**
 * Translates a given text using the application's translator service.
 *
 * This function wraps the translator's `translate` method, allowing
 * for text translation with optional pluralization and argument substitution.
 *
 * @param string $text The text to be translated.
 * @param int|string|array $arg The number of arguments to replace placeholders in the translated text.
 * @param array $args Optional arguments for replacing placeholders in the text.
 * 
 * @return string The translated text or original text if translation is unavailable.
 */
function __(string $text, int|string|array $arg = 0, array $args = []): string
{
    return application::$app->translator->translate($text, $arg, $args);
}

/**
 * Create a new Vite instance.
 *
 * This function initializes a new Vite instance with the given configuration.
 * The Vite instance provides a convenient interface for interacting with the
 * development server and production build processes.
 *
 * @param array $config The configuration for the Vite instance.
 *
 * @return vite The Vite instance initialized with the given configuration.
 */
function vite($config): vite
{
    return new vite($config);
}

/**
 * Retrieve and sanitize input data from the current request.
 *
 * This function fetches the input data from the current request and applies
 * the specified filter. The data is then passed through a sanitizer to ensure
 * it is safe for further processing.
 *
 * @param array $filter An optional array of filters to apply to the input data.
 * @return sanitizer An instance of the sanitizer containing the sanitized input data.
 */
function input(array $filter = []): sanitizer
{
    return new sanitizer(application::$app->request->all($filter));
}

/**
 * Validates the given data against a set of rules.
 *
 * @param array $rules An array of validation rules to apply.
 * @param array $data The data to be validated.
 * @return sanitizer Returns a sanitizer object if validation passes.
 * @throws Exception Throws an exception if validation fails, with the first error message or a default message.
 */
function validator(array $rules, array $data): sanitizer
{
    $validator = new validator();
    $result = $validator->validate($rules, $data);

    if ($result) {
        return new sanitizer($result);
    }

    throw new Exception($validator->getFirstError() ?? 'validation failed');
}

/**
 * Escapes a string for safe output in HTML by converting special characters to HTML entities.
 *
 * @param string $text The string to be escaped.
 * @return string The escaped string, safe for HTML output.
 */
function _e(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}


/**
 * Retrieves a setting from the given layer and key.
 *
 * @param string $layer The name of the layer containing the setting.
 * @param string $key The key of the setting to retrieve.
 * @param mixed $default The default value to return if the setting does not exist.
 * @return mixed The value of the setting, or the default value.
 */
function setting(string $layer, string $key, $default = null)
{
    return settings()->get($layer, $key, $default);
}

/**
 * Retrieves the global settings instance.
 *
 * The settings instance is a utility class for managing application settings.
 * It loads settings from a data repository and provides methods for retrieving
 * and modifying settings.
 *
 * @return settings The global settings instance.
 */
function settings(): settings
{
    global $settings;
    if (!isset($settings)) {
        $settings = new settings(app_dir('settings.dr'));
    }

    return $settings;
}