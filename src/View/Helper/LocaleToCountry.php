<?php declare(strict_types=1);
namespace Internationalisation\View\Helper;

use Laminas\View\Helper\AbstractHelper;

/**
 * View helper to get country from a locale id.
 *
 * Adapted from the view helper of Omeka plugin MultiLanguage/LocaleSwitcher.
 */
class LocaleToCountry extends AbstractHelper
{
    // This list comes from the list of languages in application/languages.
    protected $countriesByLocale = [
        'ar' => 'SA',
        'cs' => 'CZ',
        'en' => 'GB',
        'es' => 'ES',
        'et' => 'EE',
        'eu' => 'ES',
        'fr' => 'FR',
        'gl' => 'ES',
        'he' => 'IL',
        'hr' => 'HR',
        'id' => 'ID',
        'is' => 'IS',
        'it' => 'IT',
        'ja' => 'JP',
        'lt' => 'LT',
        'mn' => 'MN',
        'nb' => 'NO',
        'pl' => 'PL',
        'ro' => 'RO',
        'ru' => 'RU',
        'ta' => 'LK',
        'th' => 'TH',
        'uk' => 'UA',
    ];

    /**
     * Get the country from the locale, if possible.
     *
     * @param string $locale
     * @return string Uppercase two letters code, or empty.
     */
    public function __invoke($locale)
    {
        if (strlen($locale) == 2) {
            return isset($this->countriesByLocale[$locale])
                ? $this->countriesByLocale[$locale]
                : '';
        }

        $matches = [];
        if (preg_match('/^[a-z]+(?:_|-)([A-Z]+)$/i', $locale, $matches)) {
            return $matches[1];
        }

        return '';
    }
}
