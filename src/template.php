<?php

namespace hyper;

/**
 * Class template
 * 
 * Handles template rendering with optional layout
 * 
 * @package hyper
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class template
{
    /**
     * Path to the directory containing template files
     * 
     * @var string
     */
    private string $path;

    /**
     * Stores data to be passed into templates for rendering
     * 
     * @var array
     */
    private array $context = [];

    /**
     * Optional layout template to wrap around the main template content
     * 
     * @var string
     */
    private string $layout;

    /**
     * Initializes the template path.
     *
     * @param string|null $path Optional base path for templates. Defaults to the application path.
     */
    public function __construct(?string $path = null)
    {
        $this->setPath(
            $path ?? application::$app->path
        );
    }

    /**
     * Sets the base path for templates.
     *
     * @param string $path The directory path for the template files.
     * @return self
     */
    public function setPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    /**
     * Assigns a key-value pair to the template context.
     *
     * @param string $key The context key.
     * @param mixed $value The value associated with the key.
     * @return self
     */
    public function set(string $key, mixed $value): self
    {
        $this->context[$key] = $value;
        return $this;
    }

    /**
     * Removes specified keys from the template context.
     *
     * @param mixed ...$keys The context keys to remove.
     * @return self
     */
    public function remove(...$keys): self
    {
        foreach ($keys as $key) {
            unset($this->context[$key]);
        }

        return $this;
    }

    /**
     * Sets the layout template.
     *
     * @param string $layout The layout template file name.
     * @return self
     */
    public function layout(string $layout): self
    {
        $this->layout = $layout;
        return $this;
    }

    /**
     * Renders the specified template with the provided context.
     * If the request is AJAX-based, returns a JSON response.
     *
     * @param string $template The template file to render.
     * @param array $context Optional additional context.
     * @return string The rendered content or JSON response for AJAX requests.
     */
    public function render(string $template, array $context = []): string
    {
        $content = $this->include($template, $context);

        // Return a template part with layout.
        if (isset($this->layout)) {
            return $this->include($this->layout, ['content' => $content]);
        }

        // Returns only template part.
        return $content;
    }

    /**
     * Loads and renders the specified template file with the given context.
     *
     * @param string $template The template file name.
     * @param array $context Optional additional context to merge with the existing context.
     * @return string The rendered template content.
     */
    public function include(string $template, array $context = []): string
    {
        // Create a template location path with template root dir.
        $templatePath = $this->path . '/templates/' . str_replace('.php', '', $template) . '.php';

        // Extract and pass variables from array.
        $context = array_merge($this->context, $context);
        extract($context);

        // Set current object to be used in template.
        $template = $this;

        // Include template part and return as string.
        ob_start();
        include $templatePath;
        return ob_get_clean();
    }
}
