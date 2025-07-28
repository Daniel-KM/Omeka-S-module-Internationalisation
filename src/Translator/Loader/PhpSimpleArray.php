<?php

namespace Internationalisation\Translator\Loader;

use Laminas\I18n\Exception;
use Laminas\I18n\Translator\Loader\RemoteLoaderInterface;
use Laminas\I18n\Translator\Plural\Rule as PluralRule;
use Laminas\I18n\Translator\TextDomain;

/**
 * PHP simple array loader.
 *
 * Unlike PhpArray, PhpMemoryArray adds check for any domain and locale, but
 * the list of locales is unknown in omeka and PhpArray can be used only with a
 * file. Instead of creating a fake file (or a php:// file), this loader uses
 * the same checks rules than files, but for the remote loader interface.
 *
 * Furthermore, PhpMemoryArray prepares TextDomain with [textdomain][locale],
 * because there is not multiple files, but only one array, but this array must
 * contains [textdomain][locale] that may not exist, else an exception is thrown.
 * Here, an empty translation is returned.
 *
 * @see \Laminas\I18n\Translator\Loader\PhpArray
 * @see \Laminas\I18n\Translator\Loader\PhpMemoryArray
 * @see https://docs.laminas.dev/laminas-i18n/translator/factory
 */
class PhpSimpleArray implements RemoteLoaderInterface
{
    /**
     * @var array
     */
    protected $messages;

    /**
     * @param array $messages
     */
    public function __construct(array $messages = [])
    {
        $this->messages = $messages;
    }

    /**
     * Load translations from an array.
     *
     * @param  string $locale
     * @param  string $textDomain
     * @return TextDomain
     * @throws Exception\InvalidArgumentException
     */
    public function load($locale, $textDomain)
    {
        // The text domain is a list of translations.
        $textDomain = new TextDomain($this->messages[$textDomain][$locale] ?? []);

        if ($textDomain->offsetExists('')) {
            if (isset($textDomain['']['plural_forms'])) {
                $textDomain->setPluralRule(
                    PluralRule::fromString($textDomain['']['plural_forms'])
                );
            }
            unset($textDomain['']);
        }

        return $textDomain;
    }
}
