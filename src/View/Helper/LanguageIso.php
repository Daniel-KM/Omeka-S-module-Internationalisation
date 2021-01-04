<?php declare(strict_types=1);
namespace Internationalisation\View\Helper;

require_once dirname(__DIR__, 3) . '/vendor/daniel-km/simple-iso-639-3/src/Iso639p3.php';

use Iso639p3;
use Laminas\View\Helper\AbstractHelper;

class LanguageIso extends AbstractHelper
{
    /**
     * Get a normalized three letters language code from a two or three letters
     * one, or language and country, or from an IETF RFC 4646 language tag, or
     * from the English normalized name, raw or inverted.
     *
     * @param string $language
     * @return self|string If language doesn't exist, an empty string is returned.
     */
    public function __invoke($language = null)
    {
        return is_null($language)
            ? $this
            : Iso639p3::code($language);
    }

    /**
     * Get a normalized three letters language code from a two or three letters
     * one, or language and country, or from an IETF RFC 4646 language tag, or
     * from the English normalized name, raw or inverted.
     *
     * @uses Iso639p3::code()
     * @param string $language
     * @return string If language doesn't exist, an empty string is returned.
     */
    public function code($language)
    {
        return Iso639p3::code($language);
    }

    /**
     * Alias of code().
     *
     * @uses Iso639p3::code()
     * @param string $language
     * @return string
     */
    public function code3letters($language)
    {
        return Iso639p3::code3letters($language);
    }

    /**
     * Get a normalized two letters language code from a two or three-letters
     * one, or language and country, or from an IETF RFC 4646 language tag, or
     * from the English normalized name, raw or inverted.
     *
     * @uses Iso639p3::code2letters()
     * @param string $language
     * @return string If language doesn't exist, an empty string is returned.
     */
    public function code2letters($language)
    {
        return Iso639p3::code2letters($language);
    }

    /**
     * Get all variant codes of a language (generally only one, except some
     * languages).
     *
     * Examples: fr_FR => [fr, fra, fre]; or Français => [fr, fra, fre].
     *
     * @uses Iso639p3::codes()
     * @param string $language
     * @return array
     */
    public function codes($language)
    {
        $code = self::code($language);
        return $code
            ? array_keys(self::CODES, $code)
            : [];
    }

    /**
     * Get the native language name from a language string, if available.
     *
     * @uses Iso639p3::name()
     * @param string $language
     * @return string If language doesn't exist, an empty string is returned.
     */
    public function name($language)
    {
        return Iso639p3::name($language);
    }

    /**
     * Get the language name in English from a language string.
     *
     * @uses Iso639p3::englishName()
     * @param string $language
     * @return string If language doesn't exist, an empty string is returned.
     */
    public function englishName($language)
    {
        return Iso639p3::englishName($language);
    }

    /**
     * Get the language inverted name in English from a language string.
     *
     * The inverted language is used to simplify listing (ordered by root
     * language).
     *
     * @uses Iso639p3::englishInvertedName()
     * @param string $language
     * @return string If language doesn't exist, an empty string is returned.
     */
    public function englishInvertedName($language)
    {
        return Iso639p3::englishInvertedName($language);
    }
}
