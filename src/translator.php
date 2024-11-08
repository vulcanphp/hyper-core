<?php

namespace hyper;

/**
 * Class translator
 * 
 * Translator class for handling multilingual text translations.
 * 
 * @package hyper
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 * @version 1.0.1
 */
class translator
{
    /**
     * Stores translated texts.
     * 
     * @var array
     */
    private array $translatedTexts;

    /**
     * Constructor
     * 
     * Initializes the translator with the given language and file directory.
     * 
     * @param string $lang The language code.
     * @param string $dir The directory containing the language files.
     */
    public function __construct(private string $lang, string $dir)
    {
        $this->translatedTexts = require "{$dir}/{$lang}.php";
    }

    /**
     * Translates a given text based on the local translations or returns the original text if translation is unavailable.
     * Supports pluralization and argument substitution.
     *
     * @param string $text The text to be translated.
     * @param int|string|array $arg The number of arguments to replace placeholders in the translated text.
     * @param array $args An array of arguments to replace placeholders in the translated text.
     * @return string The translated text with any placeholders replaced by the provided arguments.
     */
    public function translate(string $text, int|string|array $arg = 0, array $args = []): string
    {
        // Check if the text has a translation
        $translation = $this->translatedTexts[$text] ?? $text;

        // Determine if the translation has plural forms
        if (is_array($translation)) {
            $translation = $arg > 1 ? $translation[1] : $translation[0];
        }

        // Determine if the translation has arguments, else substitute with the number.
        if ($arg !== 0 && empty($args)) {
            $args = is_array($arg) ? $arg : [$arg];
        }

        // Use vsprintf to substitute any placeholders with args
        return vsprintf($translation, $args);
    }
}
