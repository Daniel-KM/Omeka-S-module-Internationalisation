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
            'languageSwitcher' => Service\ViewHelper\LanguageSwitcherFactory::class,
            'localeValue' => Service\ViewHelper\LocaleValueFactory::class,
        ],
    ],
    'block_layouts' => [
        'factories' => [
            'mirrorPage' => Service\BlockLayout\MirrorPageFactory::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\MirrorPageFieldset::class => Form\MirrorPageFieldset::class,
            Form\SettingsFieldset::class => Form\SettingsFieldset::class,
        ],
        'factories' => [
            Form\Element\SitesPageSelect::class => Service\Form\Element\SitesPageSelectFactory::class,
            Form\DuplicateSiteFieldset::class => \Laminas\Form\ElementFactory::class,
            Form\SiteSettingsFieldset::class => Service\Form\SiteSettingsFieldsetFactory::class,
            \Omeka\Form\SitePageForm::class => Service\Form\SitePageFormFactory::class,
        ],
    ],
    'controller_plugins' => [
        'factories' => [
            'listSiteGroups' => Service\ControllerPlugin\ListSiteGroupsFactory::class,
        ],
    ],
    'translator' => [
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
            'internationalisation_site_groups' => [],
        ],
        'site_settings' => [
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
        ],
    ],
];
