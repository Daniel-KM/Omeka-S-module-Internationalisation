<?php
namespace LanguageSwitcher;

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
    'form_elements' => [
        'invokables' => [
            Form\SettingsFieldset::class => Form\SettingsFieldset::class,
        ],
        'factories' => [
            \Omeka\Form\SitePageForm::class => Service\Form\SitePageFormFactory::class,
            Form\Element\SitesPageSelect::class => Service\Form\Element\SitesPageSelectFactory::class,
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
    'languageswitcher' => [
        'settings' => [
            'languageswitcher_site_groups' => [],
        ],
    ],
];
