<?php declare(strict_types=1);

namespace Internationalisation;

return [
    'listeners' => [
        Mvc\MvcListeners::class,
    ],
    'service_manager' => [
        'invokables' => [
            Mvc\MvcListeners::class => Mvc\MvcListeners::class,
        ],
    ],
    'api_adapters' => [
        'invokables' => [
            'site_page_relations' => Api\Adapter\SitePageRelationAdapter::class,
            'translations' => Api\Adapter\TranslationAdapter::class,
        ],
    ],
    'entity_manager' => [
        'mapping_classes_paths' => [
            dirname(__DIR__) . '/src/Entity',
        ],
        'proxy_paths' => [
            dirname(__DIR__) . '/data/doctrine-proxies',
        ],
    ],
    'view_manager' => [
        'template_path_stack' => [
            dirname(__DIR__) . '/view',
        ],
    ],
    'view_helpers' => [
        'invokables' => [
            'localeToCountry' => View\Helper\LocaleToCountry::class,
        ],
        'factories' => [
            'languageIso' => Service\ViewHelper\LanguageIsoFactory::class,
            'languageList' => Service\ViewHelper\LanguageListFactory::class,
            'languageSwitcher' => Service\ViewHelper\LanguageSwitcherFactory::class,
            'localeValue' => Service\ViewHelper\LocaleValueFactory::class,
        ],
    ],
    'block_layouts' => [
        'invokables' => [
            'languageSwitcher' => Site\BlockLayout\LanguageSwitcher::class,
        ],
        'factories' => [
            'mirrorPage' => Service\BlockLayout\MirrorPageFactory::class,
        ],
    ],
    'resource_page_block_layouts' => [
        'invokables' => [
            'languageSwitcher' => Site\ResourcePageBlockLayout\LanguageSwitcher::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\LanguageSwitcherFieldset::class => Form\LanguageSwitcherFieldset::class,
            Form\MirrorPageFieldset::class => Form\MirrorPageFieldset::class,
            Form\SettingsFieldset::class => Form\SettingsFieldset::class,
        ],
        'factories' => [
            // TODO To be removed when Common 3.4.64 will be released.
            Form\Element\SitesPageSelect::class => Service\Form\Element\SitesPageSelectFactory::class,
            Form\DuplicateSiteFieldset::class => \Laminas\Form\ElementFactory::class,
            Form\SiteSettingsFieldset::class => Service\Form\SiteSettingsFieldsetFactory::class,
            Form\SitePageForm::class => Service\Form\SitePageFormFactory::class,
        ],
        'aliases' => [
            // The site page form does not implement form events, so override it for now.
            \Omeka\Form\SitePageForm::class => Form\SitePageForm::class,
        ],
    ],
    'controller_plugins' => [
        'factories' => [
            'listSiteGroups' => Service\ControllerPlugin\ListSiteGroupsFactory::class,
        ],
    ],
    /**
     * To manage translation directly from the database, see:
     * @see https://docs.laminas.dev/laminas-i18n/translator/factory
     * @see https://stackoverflow.com/questions/44406391/zend-framework-3-translator-from-db#answer-44413709
     */
    'translator' => [
        'loaderpluginmanager' => [
            'invokables' => [
                // TODO Create a PhpTableArray that load the current locale only and the other locales on demand.
                // TODO But it is not so important, because tables are generally few and small, only for missing or specific translations.
                Translator\Loader\PhpSimpleArray::class => Translator\Loader\PhpSimpleArray::class,
            ],
        ],
        // The translations for "tables" are prepared as "remote_translation"
        // during bootstrap to avoid issue during upgrade of the module.
        'remote_translation' => [
            [
                'type' => 'tables',
                'text_domain' => null,
            ],
        ],
        'translation_file_patterns' => [
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
        ],
    ],
    'internationalisation' => [
        'settings' => [
            'internationalisation_translation_tables' => [],
            'internationalisation_site_groups' => [],
        ],
        'site_settings' => [
            'internationalisation_translation_tables' => [],
            'internationalisation_display_values' => 'all',
            'internationalisation_fallbacks' => [],
            'internationalisation_required_languages' => [],
            // Settings without form, automatically prepared when the form is saved.
            'internationalisation_locales' => [],
            // Kept for compatibility for Omeka < 2.1 (direct helper).
            'internationalisation_iso_codes' => [],
        ],
        'block_settings' => [
            'mirrorPage' => [
                'page' => null,
            ],
            'languageSwitcher' => [
                'display_locale' => null,
            ],
        ],
    ],
];
