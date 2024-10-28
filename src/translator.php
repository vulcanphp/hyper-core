<?php

namespace hyper;

use hyper\utils\ping;
use Exception;
use JsonException;
use Throwable;

/**
 * Class translator
 * 
 * Translator class for handling multilingual text translations.
 * Supports local translation files and Google Translation as fallback for unknown texts.
 * 
 * @package hyper
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class translator
{
    /**
     * Stores already translated texts.
     * 
     * @var array
     */
    private array $translatedTexts;

    /**
     * Store unknown texts to be translated.
     * 
     * @var array
     */
    private array $unknownText;

    /**
     * Holds path for local translation file.
     * 
     * @var string
     */
    private string $localPath;

    /**
     * Holdes the target language.
     * 
     * @var string
     */
    private string $lang;

    /**
     * Tracks if the translation data has already been loaded.
     * 
     * @var bool
     */
    private bool $loaded = false;

    /**
     * Constructor for initializing the translator with the target language and directory path.
     *
     * @param string $lang The target language code (e.g., 'es' for Spanish).
     * @param string $dir The directory path for storing and retrieving language files.
     */
    public function __construct(string $lang, string $dir)
    {
        if ($lang != 'en') {
            $this->lang = $lang;
            $this->localPath = "{$dir}/{$lang}.json";
        }
    }

    /**
     * Loads translation data from the language file, if not already loaded.
     */
    private function reload(): void
    {
        if (!$this->loaded) {
            // Check if loaded to prevent multiple reload next time.
            $this->loaded = true;
            $this->translatedTexts = [];
            $this->unknownText = [];

            // Check if the local language file exists
            if (file_exists($this->localPath)) {
                debugger('app', "language file loading for ({$this->lang})");
                $this->translatedTexts = (array) json_decode(
                    file_get_contents($this->localPath),
                    true
                );
                debugger('app', "language file loaded");
            }
        }
    }

    /**
     * Translates a given text based on the local translations or Google API if strict mode is off.
     *
     * @param string|null $text The text to be translated.
     * @param bool $strict Whether to use only local translations and mark unknown texts.
     * @return string|null Translated text or the original if translation is unavailable.
     */
    public function translate(?string $text = '', bool $strict = false): ?string
    {
        // Check if the text is valid, else returns original text.
        if (!isset($this->lang) || empty($text) || strlen($text) >= 500 || intval($text) == $text || empty($hash = $this->getHash($text))) {
            return $text;
        }

        // Reload local translations if to loaded.
        $this->reload();

        // Check if the text already exists in the local translations.
        if ($translated = $this->translatedTexts[$hash] ?? null) {
            return $translated;
        }

        // Retuens 
        if ($strict) {
            $this->saveUnknownText([$text]);
            return $this->translatedTexts[$hash] ?? $text;
        }

        // Check if unknownText sentence exceeded more than 500.
        if (count($this->unknownText) >= 500) {
            return $text;
        }

        // If not in strict mode, add text to unknown list for Google Translation.
        $this->unknownText[] = $text;

        // Returns a hash code, replace it with actual text later.
        return "[{$hash}]";
    }

    /**
     * Generates a hash for a given text for consistent mapping in the translations array.
     *
     * @param string $text The text to be hashed.
     * @return string The generated hash for the given text.
     */
    private function getHash(string $text): string
    {
        return trim(
            strtolower(
                preg_replace(
                    '/[^a-zA-Z0-9\<\>\#\*\_]+/i',
                    '-',
                    html_entity_decode($text)
                )
            ),
            '-'
        );
    }

    /**
     * Translates an array of texts using Google's free translation API.
     *
     * @param array $texts The texts to be translated.
     * @return array Associative array of original texts to their translations.
     * @throws Exception if Google blocks the request or there is a parsing issue.
     */
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
                    'sl' => 'en',
                    'tl' => $this->lang,
                    'q' => $translate
                ]))
            );

            if ($response['status'] != 200) {
                throw new Exception('Unexpected Status from google');
            }

            // Clean up response body for JSON decoding
            $bodyJson = preg_replace(['/,+/', '/\[,/'], [',', '['], $response['body']);
        } catch (Throwable $e) {
            throw new Exception('Google detected unusual traffic from your computer network, try again later (2 - 48 hours)');
        }

        // Decode JSON data from Google response
        try {
            $sentencesArray = json_decode($bodyJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new JsonException('Data cannot be decoded or it is deeper than the recursion limit');
        }

        foreach ($sentencesArray[0] as $sentence) {
            $translated[] = isset($sentence[0]) ? ' ' . $sentence[0] : '';
        }

        // Extract multiple texts with {$separator} from single string.
        $translated = array_filter(
            array_map(
                fn ($sentence) => trim(str_replace("\n", '', $sentence)),
                explode($separator, str_replace($replace, $separator, join('', $translated)))
            )
        );

        // Return Original_Text => Trabslated_Text, elase return empty array.
        return count($texts) === count($translated) ? array_filter(array_combine($texts, $translated)) : [];
    }

    /**
     * Saves unknown text by translating it via Google and storing in the local language file.
     *
     * @param array $texts The texts to be saved after translation.
     */
    private function saveUnknownText(array $texts): void
    {
        // Push each translated text into $translatedTexts to locally save it.
        foreach ($this->translateFromGoogle($texts) as $original => $translated) {
            $this->translatedTexts[$this->getHash($original)] = $translated;
        }

        // Save translations to local file.
        file_put_contents(
            $this->localPath,
            json_encode($this->translatedTexts, JSON_UNESCAPED_UNICODE)
        );
        debugger('app', "language file saved for ({$this->lang})");
    }

    /**
     * Saves any unknown texts at the end of the request lifecycle and applies output filters.
     * 
     * @return void
     */
    public function save(): void
    {
        if (isset($this->unknownText) && !empty($this->unknownText)) {
            // Translate unknown texts from google and save it into localpath.
            $this->saveUnknownText($this->unknownText);

            // Add a output filter into response to replace hash code with text.
            application::$app->response->addOutputFilter(
                function ($content) {
                    foreach ($this->unknownText as $text) {
                        $hash = $this->getHash($text);

                        // Replace previous hash code with actual text.
                        $content = str_replace(
                            "[{$hash}]",
                            $this->translatedTexts[$hash] ?? $text,
                            $content
                        );
                    }

                    // Retuen the modified output to client.
                    return $content;
                }
            );
        }
    }
}
