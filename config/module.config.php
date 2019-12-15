<?php
namespace Internationalisation;

return [
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
            'localeValue' => View\Helper\LocaleValue::class,
        ],
        'factories' => [
            'languageIso' => Service\ViewHelper\LanguageIsoFactory::class,
            'languageSwitcher' => Service\ViewHelper\LanguageSwitcherFactory::class,
        ],
    ],
    'block_layouts' => [
        'factories' => [
            'simplePage' => Service\BlockLayout\SimplePageFactory::class,
        ],
    ],
    'form_elements' => [
        'invokables' => [
            Form\SettingsFieldset::class => Form\SettingsFieldset::class,
            Form\SimplePageFieldset::class => Form\SimplePageFieldset::class,
        ],
        'factories' => [
            Form\Element\SitesPageSelect::class => Service\Form\Element\SitesPageSelectFactory::class,
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
            'internationalisation_iso_codes' => [],
            'internationalisation_fallbacks' => [],
            'internationalisation_required_languages' => [],
        ],
        'block_settings' => [
            'simplePage' => [
                'page' => null,
            ],
        ],
    ],
];
