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
    // It allows to get the right country in most of the cases.
    protected $countriesByLocale = [
        'ar' => 'SA',
        'bg' => 'BG',
        'bg-BG' => 'BG',
        'bg_BG' => 'BG',
        'ca' => 'ES',
        'ca-ES' => 'ES',
        'ca_ES' => 'ES',
        'cs' => 'CZ',
        'de' => 'DE',
        'de-DE' => 'DE',
        'de_DE' => 'DE',
        'el' => 'GR',
        'el-GR' => 'GR',
        'el_GR' => 'GR',
        'en' => 'GB',
        // 'es_419' => 'Latin America',
        'es' => 'ES',
        'et' => 'EE',
        'eu' => 'ES',
        'fi' => 'FI',
        'fi-FI' => 'FI',
        'fi_FI' => 'FI',
        'fr' => 'FR',
        'gl' => 'ES',
        'he' => 'IL',
        'hr' => 'HR',
        'hu' => 'HU',
        'hu-HU' => 'HU',
        'hu_HU' => 'HU',
        'id' => 'ID',
        'is' => 'IS',
        'it' => 'IT',
        'ja' => 'JP',
        'lt' => 'LT',
        'ko' => 'KR',
        'ko-KR' => 'KR',
        'ko_KR' => 'KR',
        'mn' => 'MN',
        'nb' => 'NO',
        'nl' => 'NL',
        'nl-NL' => 'NL',
        'nl_NL' => 'NL',
        'pl' => 'PL',
        // pt: Portugal or Bresil.
        // 'pt' => 'BR',
        'pt' => 'PT',
        'pt-BR' => 'BR',
        'pt_BR' => 'BR',
        'pt-PT' => 'PT',
        'pt_PT' => 'PT',
        'pt' => 'PT',
        'ro' => 'RO',
        'ru' => 'RU',
        'sr' => 'RS',
        'sr-RS' => 'RS',
        'sr_RS' => 'RS',
        'sv' => 'SE',
        'sv-SE' => 'SE',
        'sv_SE' => 'SE',
        'ta' => 'LK',
        'th' => 'TH',
        'tr' => 'TR',
        'tr-TR' => 'TR',
        'tr_TR' => 'TR',
        'uk' => 'UA',
        // zh: China or Taiwan.
        'zh' => 'CN',
        'zh-CN' => 'CN',
        'zh_CN' => 'CN',
        'zh-TW' => 'TW',
        'zh_TW' => 'TW',
    ];

    /**
     * Get the country from the locale, if possible.
     *
     * @param string $locale
     * @return string Uppercase two letters code, or empty string.
     */
    public function __invoke(string $locale): string
    {
        if (isset($this->countriesByLocale[$locale])) {
            return $this->countriesByLocale[$locale];
        }

        if (strlen($locale) === 2) {
            return '';
        }

        $matches = [];
        if (preg_match('/^[a-z]+(?:_|-)([A-Z]+)$/i', $locale, $matches)) {
            return $matches[1];
        }

        return '';
    }
}
