<?php

namespace hyper;

use Throwable;

class debugger
{
    private array $logs = [];

    public function __construct(protected bool $debug = false)
    {
        set_error_handler([$this, 'handleError']);
        set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);
    }

    public function log(string $type,  mixed $log): void
    {
        if ($this->debug) {
            $this->logs[$type][] = [
                'message' => is_string($log) ? $log : serialize($log),
                'time' => time(),
                'memory_usage' => memory_get_usage()
            ];
        }
    }

    public function handleError(int $errno, string $errstr, string $errfile, int $errline): void
    {
        $this->renderError('Error', $errstr, $errfile, $errline);
    }

    public function handleException(Throwable $exception): void
    {
        $this->renderError(
            'Exception',
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTrace()
        );
        exit(0);
    }

    public function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error !== null) {
            $this->renderError('Shutdown Error', $error['message'], $error['file'], $error['line']);
        }
    }

    private function renderError(string $type, string $message, string $file, int $line, array $trace = []): void
    {
        if (!headers_sent()) {
            http_response_code(500);
        }
        if ($this->debug) {
            echo <<<HTML
                <!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>{$type}: {$message}</title>
                    <style>* {margin: 0;padding: 0;}body { font-family: Arial, sans-serif; line-height: 1.6; margin: 0; padding: 0; }.container { padding: 20px; }.error-box { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; padding: 20px; }.trace-box { background-color: #f1f1f1; padding: 10px; border: 1px solid #ccc; margin-top: 10px; margin-top: 20px; }.trace-box pre { margin: 0; }h1 { font-size: 24px; margin: 0 0 10px; }p { margin: 0 0 10px; }.file { font-weight: bold; }.line { font-weight: bold; color: #d9534f; }</style>
                </head>
                <body>
                    <div class="container">
                        <div class="error-box">
                            <h1>{$type}: {$message}</h1>
                            <p><span class="file">{$file}</span> at line <span class="line">{$line}</span></p>
                        </div>
                        {$this->formatTrace($trace)}
                    </div>
                </body>
                </html>
            HTML;
        } else {
            echo <<<HTML
                <!DOCTYPE html>
                    <html lang="en">
                    <head>
                        <meta charset="UTF-8">
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <title>Oops: There is a Error!</title>
                        <style>* {margin: 0;padding: 0;}body { font-family: Arial, sans-serif; line-height: 1.6; margin: 0; padding: 0; }.container { padding: 20px; max-width: 960px;margin:auto; text-align:center; }.error-box { background-color: #fefce8; color: #ca8a04; border: 1px solid #fef08a; padding: 20px; }h1 { font-size: 28px; margin: 0 0 10px; }p { margin: 0 0 10px; font-size:16px; }</style>
                    </head>
                    <body>
                        <div class="container">
                            <div class="error-box">
                                <h1>Oops!!</h1>
                                <p>{$message} in <b>{$file}</b> on line <b>{$line}</b></p><p>There has been a critical error on this website.</p>
                            </div>
                        </div>
                    </body>
                </html>
            HTML;
        }
    }

    private function formatTrace(array $trace): string
    {
        $traceHtml = '';
        foreach ($trace as $index => $frame) {
            $file = $frame['file'] ?? '[internal function]';
            $line = $frame['line'] ?? '';
            $function = $frame['function'] ?? '';
            $class = $frame['class'] ?? '';
            $type = $frame['type'] ?? '';
            $args = isset($frame['args']) ? $this->formatArgs($frame['args']) : '';
            $traceHtml .= "#{$index} {$file}({$line}): {$class}{$type}{$function}({$args})\n";
        }
        if (!empty($traceHtml)) {
            $traceHtml = htmlspecialchars($traceHtml, ENT_QUOTES, 'UTF-8');
            $traceHtml = <<<HTML
                <div class="trace-box">
                    <h2>Stack Trace</h2>
                    <pre>{$traceHtml}</pre>
                </div>
            HTML;
        }
        return $traceHtml;
    }

    private function formatArgs(array $args): string
    {
        return implode(', ', array_map(fn ($arg) => is_object($arg) ? get_class($arg) : gettype($arg), $args));
    }

    public function __destruct()
    {
        if ($this->debug && strpos($_SERVER['HTTP_ACCEPT'], 'text/html') !== false) {
            $html = '<style>.debugger-container {position: fixed;opacity: 0.98;bottom: 0;left: 0;width: 100%;background: #333;color: #fff;font-family: Arial, sans-serif;font-size: 12px;box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.3);z-index: 9999;}.debugger-header {background: #444;padding: 5px 10px;border-bottom: 1px solid #555;cursor: pointer;}.debugger-tabs {display: flex;cursor: pointer;border-bottom: 1px solid #555;}.debugger-tab {flex: 1;padding: 5px 10px;text-align: center;background: #444;border-right: 1px solid #555;transition: background 0.3s;}.debugger-tab:hover {background: #555;}.debugger-tab.active {background: #555;font-weight: bold;}.debugger-content {display: none;max-height: 35vh;overflow-y: auto;padding: 5px 10px;}.debugger-content.active {display: block;}.debugger-log {margin-bottom: 10px;}.debugger-log-type {font-weight: bold;color: #c33;}.debugger-log-message {margin: 2px 0;}.debugger-log-time,.debugger-log-memory {font-size: 10px;color: #aaa;}</style>';
            $html .= '<div class="debugger-container">';
            $html .= '<div class="debugger-header">Debug Logs (Click to toggle)</div>';
            $html .= '<div class="debugger-wrapper" style="display:none;"><div class="debugger-tabs">';

            foreach ($this->logs as $type => $entries) {
                $html .= '<div class="debugger-tab">' . htmlspecialchars($type) . ' (' . count($entries) . ')' . '</div>';
            }

            $html .= '</div>';
            foreach ($this->logs as $type => $entries) {
                $html .= '<div class="debugger-content">';

                foreach ($entries as $entry) {
                    $html .= '<div class="debugger-log">';
                    $html .= '<div class="debugger-log-type">' . htmlspecialchars($type) . '</div>';
                    $html .= '<div class="debugger-log-message">' . htmlspecialchars($entry['message']) . '</div>';
                    $html .= '<div class="debugger-log-time">Time: ' . date('Y-m-d H:i:s', $entry['time']) . '</div>';
                    $html .= '<div class="debugger-log-memory">Memory Usage: ' . number_format($entry['memory_usage'] / 1024, 2) . ' KB</div>';
                    $html .= '</div>';
                }

                $html .= '</div>';
            }

            $html .= '</div></div>';
            $html .= '<script>document.querySelector(".debugger-header").addEventListener("click", function() {var tabs = document.querySelectorAll(".debugger-tab");var contents = document.querySelectorAll(".debugger-content");tabs.forEach(function(tab, index) {tab.classList.remove("active");contents[index].classList.remove("active");});tabs[0].classList.add("active");contents[0].classList.add("active");document.querySelector(".debugger-wrapper").style.display = document.querySelector(".debugger-wrapper").style.display === "none" ? "block" : "none";});document.querySelectorAll(".debugger-tab").forEach(function(tab, index) {tab.addEventListener("click", function() {document.querySelectorAll(".debugger-tab").forEach(function(t, i) {t.classList.remove("active");document.querySelectorAll(".debugger-content")[i].classList.remove("active");});tab.classList.add("active");document.querySelectorAll(".debugger-content")[index].classList.add("active");});});</script>';

            echo $html;
        }
    }
}
