<?php

namespace hyper;

class template
{
    private string $path;
    private array $context = [];
    private ?string $layout = null;

    public function __construct(?string $path = null)
    {
        $this->setPath($path ?? application::$app->path);
    }

    public function setPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    public function set(string $key, mixed $value): self
    {
        $this->context[$key] = $value;
        return $this;
    }

    public function layout(string $layout): self
    {
        $this->layout = $layout;
        return $this;
    }

    public function render(string $template, array $context = []): string
    {
        $content = $this->template($template, $context);
        if ($this->layout) {
            return $this->template($this->layout, ['content' => $content]);
        }
        return $content;
    }

    public function template(string $template, array $context = []): string
    {
        $template = $this->path . '/templates/' . str_replace('.php', '', $template) . '.php';
        debugger('template', "template rendering from: {$template}");
        $context = array_merge($this->context, $context);
        extract($context);
        ob_start();
        include $template;
        return ob_get_clean();
    }
}
