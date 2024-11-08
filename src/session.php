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
 * @version 1.0.1
 */
class session
{
    /**
     * The current session status.
     * 
     * This property stores the current session status, which can be
     * one of the following constants:
     * 
     * - PHP_SESSION_NONE: No session is associated with the request.
     * - PHP_SESSION_ACTIVE: A session is associated with the request.
     * - PHP_SESSION_DISABLED: Sessions are unavailable.
     * 
     * @var int
     * @see https://www.php.net/manual/en/session.constants.php
     */
    public int $session_status;

    /**
     * Constructs a new session object.
     *
     * Initializes the session status by getting the current session status.
     *
     * @return void
     */
    public function __construct()
    {
        $this->session_status = session_status();
    }

    /**
     * Checks if a session is started.
     *
     * If a session is not started, starts the session and returns true.
     * If a session is already started, returns true without starting a new session.
     *
     * @return bool True if the session is started, false otherwise.
     */
    public function check(): bool
    {
        if ($this->session_status === PHP_SESSION_NONE) {
            session_start();
            $this->session_status = session_status();
        }

        return $this->session_status;
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
        $this->check();
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
        $this->check() && $_SESSION[$key] = $value;
    }

    /**
     * Checks if a session variable exists and is not empty.
     *
     * @param string $key The session variable key to check.
     * @return bool True if the session variable exists and is not empty, false otherwise.
     */
    public function has(string $key): bool
    {
        return $this->check() && isset($_SESSION[$key]) && !empty($_SESSION[$key]);
    }

    /**
     * Deletes a session variable by key.
     *
     * @param string $key The session variable key to delete.
     * @return void
     */
    public function delete(string $key): void
    {
        $this->check();
        unset($_SESSION[$key]);
    }

    /**
     * Regenerates the session ID to prevent session fixation attacks.
     * Optionally deletes the old session file.
     *
     * @return void
     */
    public function regenerate(bool $deleteOldSession = false): bool
    {
        return $this->check() && session_regenerate_id($deleteOldSession);
    }

    /**
     * Destroys the current session and deletes all session data.
     *
     * @return void
     */
    public function destroy(): bool
    {
        return $this->check() && session_destroy();
    }

    /**
     * Returns the current session ID.
     *
     * @return string The current session ID.
     */
    public function id(): string
    {
        $this->check();
        return session_id();
    }
}
