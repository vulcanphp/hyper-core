<?php

namespace hyper;

/**
 * Class session
 * 
 * Manages session data for the application, providing methods to store,
 * retrieve, check, and delete session variables, as well as regenerate and
 * destroy the session.
 * 
 * @package hyper
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class session
{
    /**
     * session constructor.
     * Starts the session if it has not already been started.
     */
    public function __construct()
    {
        session_start();
    }

    /**
     * Retrieves a value from the session by key.
     *
     * @param string $key The session variable key to retrieve.
     * @param mixed $default Optional default value to return if the key does not exist.
     * @return mixed The session value if it exists, or the default value.
     */
    public function get(string $key, $default = null)
    {
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Sets a session variable with a specified key and value.
     *
     * @param string $key The session variable key to set.
     * @param mixed $value The value to store in the session.
     * @return void
     */
    public function set(string $key, $value): void
    {
        $_SESSION[$key] = $value;
    }

    /**
     * Checks if a session variable exists and is not empty.
     *
     * @param string $key The session variable key to check.
     * @return bool True if the session variable exists and is not empty, false otherwise.
     */
    public function has(string $key): bool
    {
        return isset($_SESSION[$key]) && !empty($_SESSION[$key]);
    }

    /**
     * Deletes a session variable by key.
     *
     * @param string $key The session variable key to delete.
     * @return void
     */
    public function delete(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Regenerates the session ID to prevent session fixation attacks.
     * Optionally deletes the old session file.
     *
     * @return void
     */
    public function regenerate(): void
    {
        session_regenerate_id(true);
    }

    /**
     * Destroys the current session and deletes all session data.
     *
     * @return void
     */
    public function destroy(): void
    {
        session_destroy();
    }
}
