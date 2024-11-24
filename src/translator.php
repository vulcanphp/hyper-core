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
     * Initializes the translator with the given translations.
     * 
     * The provided translations are used to replace placeholders in translated text.
     * 
     * @param array $translatedTexts The translations to use.
     */
    public function __construct(private array $translatedTexts = [])
    {
    }

    /**
     * Merges the given translations with the existing ones.
     * 
     * Useful for adding or overriding translations for a specific context.
     * 
     * @param array $translatedTexts The translations to be merged.
     * @return void
     */
    public function mergeTranslatedTexts(array $translatedTexts)
    {
        $this->translatedTexts = array_merge($this->translatedTexts, $translatedTexts);
    }


    /**
     * Sets the translations for the translator.
     * 
     * Replaces any existing translations with the provided array of translated texts.
     * 
     * @param array $translatedTexts The translations to set.
     * @return void
     */
    public function setTranslatedTexts(array $translatedTexts)
    {
        $this->translatedTexts = $translatedTexts;
    }

    /**
     * Translates a given text based on the local translations or returns the original text if translation is unavailable.
     * Supports pluralization and argument substitution.
     *
     * @param string $text The text to be translated.
     * @param $arg The number to determine pluralization or replace placeholder in the translated text.
     * @param array $args An array of arguments to replace placeholders in the translated text.
     * @param array $args2 An array of arguments to replace plural placeholders in the translated text.
     * @return string The translated text with any placeholders replaced by the provided arguments.
     */
    public function translate(string $text, $arg = null, array $args = [], array $args2 = []): string
    {
        // Check if the text has a translation
        $translation = $this->translatedTexts[$text] ?? $text;

        // Determine if the translation has plural forms
        if (is_array($translation)) {
            $translation = $arg > 1 ? $translation[1] : $translation[0];
            $args = $arg > 1 && !empty($args2) ? $args2 : $args;
        } elseif (!empty($args) && !empty($args2)) {
            $args = $arg > 1 ? $args2 : $args;
        }

        // Determine if the translation has arguments, else substitute with the first argument.
        if ($arg !== null && empty($args)) {
            $args = is_array($arg) ? $arg : [$arg];
        }

        // Use vsprintf to substitute any placeholders with args
        return vsprintf($translation, $args);
    }
}
