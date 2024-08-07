<?php

namespace hyper;

use hyper\utils\ping;
use Exception;
use JsonException;
use Throwable;

class translator
{
    private array $translatedTexts, $unknownText;
    private string $localPath, $lang;
    private bool $loaded = false;

    public function __construct(string $lang, string $dir)
    {
        if ($lang != 'en') {
            $this->lang = $lang;
            $this->localPath = "{$dir}/{$lang}.json";
        }
    }

    private function reload(): void
    {
        if (!$this->loaded) {
            $this->loaded = true;
            $this->translatedTexts = [];

            if (file_exists($this->localPath)) {
                debugger('app', "language file loading for ({$this->lang})");
                $this->translatedTexts = (array) json_decode(file_get_contents($this->localPath), true);
                debugger('app', "language file loaded");
            }
        }
    }

    public function translate(?string $text = '', bool $strict = false): ?string
    {
        if (!isset($this->lang) || empty($text) || strlen($text) >= 1000 || intval($text) == $text || empty($hash = $this->getHash($text))) {
            return $text;
        }

        $this->reload();

        if ($translated = $this->translatedTexts[$hash] ?? null) {
            return $translated;
        }

        if ($strict) {
            $this->saveUnknownText([$text]);
            return $this->translatedTexts[$hash] ?? $text;
        }

        $this->unknownText[] = $text;
        return "[{$hash}]";
    }

    private function getHash(string $text): string
    {
        return trim(strtolower(preg_replace('/[^a-zA-Z0-9\<\>\#\*\_]+/i', '-', html_entity_decode($text))), '-');
    }

    private function translateFromGoogle(array $texts): array
    {
        $separator  = '(##)';
        $replace    = ['( # # )', '( ## )', '( ##)', '(## )'];
        $texts      = array_filter(array_map(fn ($text) => trim($text), $texts));
        $translate  = join("\n$separator", $texts);
        $translated = [];

        debugger('app', "translating texts from google: {$translate}");

        try {
            $http = new ping();
            $response = $http->get(
                'https://translate.google.com/translate_a/single?' . preg_replace('/%5B\d+%5D=/', '=', http_build_query([
                    'client' => 'gtx',
                    'dt' => ['t'],
                    'ie' => 'utf-8',
                    'oe' => 'utf-8',
                    'sl' => 'en', // source language
                    'tl' => $this->lang,
                    'q' => $translate
                ]))
            );

            if ($response['status'] != 200) {
                throw new Exception('Unexpected Status from google');
            }

            // Modify body to avoid json errors
            $bodyJson = preg_replace(['/,+/', '/\[,/'], [',', '['], $response['body']);
        } catch (Throwable $e) {
            throw new Exception('Google detected unusual traffic from your computer network, try again later (2 - 48 hours)');
        }

        // Decode JSON data
        try {
            $sentencesArray = json_decode($bodyJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new JsonException('Data cannot be decoded or it is deeper than the recursion limit');
        }

        foreach ($sentencesArray[0] as $sentence) {
            $translated[] = isset($sentence[0]) ? ' ' . $sentence[0] : '';
        }

        $translated = array_filter(
            array_map(
                fn ($sentence) => trim(str_replace("\n", '', $sentence)),
                explode($separator, str_replace($replace, $separator, join('', $translated)))
            )
        );

        return count($texts) === count($translated) ? array_filter(array_combine($texts, $translated)) : [];
    }

    private function saveUnknownText(array $texts): void
    {
        foreach ($this->translateFromGoogle($texts) as $original => $translated) {
            $this->translatedTexts[$this->getHash($original)] = $translated;
        }

        file_put_contents($this->localPath, json_encode($this->translatedTexts, JSON_UNESCAPED_UNICODE));

        debugger('app', "language file saved for ({$this->lang})");
    }

    public function save(): void
    {
        if (isset($this->unknownText)) {
            $this->saveUnknownText($this->unknownText);
            application::$app->response->addOutputFilter(
                function ($content) {
                    foreach ($this->unknownText as $text) {
                        $hash = $this->getHash($text);
                        $content = str_replace("[{$hash}]", $this->translatedTexts[$hash] ?? 'N/A', $content);
                    }
                    return $content;
                }
            );
        }
    }
}
