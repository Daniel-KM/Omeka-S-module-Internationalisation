<?php declare(strict_types=1);

namespace Internationalisation;

// Instead of checking config for specific storage path during an event, check it manually here.
$configLocal = include OMEKA_PATH . '/config/local.config.php';
if (empty($configLocal['file_store']['local']['base_path'])) {
    $configLocal = include OMEKA_PATH . '/application/config/module.config.php';
    $localPath = $configLocal['file_store']['local']['base_path'] ?: OMEKA_PATH . '/files';
} else {
    $localPath = $configLocal['file_store']['local']['base_path'];
}

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
            Form\TranslationForm::class => Form\TranslationForm::class,
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
    'controllers' => [
        'factories' => [
            Controller\Admin\TranslationController::class => Service\Controller\TranslationControllerFactory::class,
        ],
    ],
    'controller_plugins' => [
        'factories' => [
            'listSiteGroups' => Service\ControllerPlugin\ListSiteGroupsFactory::class,
            'updateTranslationFiles' => Service\ControllerPlugin\UpdateTranslationFilesFactory::class,
        ],
    ],
    'router' => [
        'routes' => [
            'admin' => [
                'child_routes' => [
                    'translation' => [
                        'type' => \Laminas\Router\Http\Literal::class,
                        'options' => [
                            'route' => '/translation',
                            'defaults' => [
                                '__NAMESPACE__' => 'Internationalisation\Controller\Admin',
                                'controller' => Controller\Admin\TranslationController::class,
                                'action' => 'browse',
                            ],
                        ],
                        'may_terminate' => true,
                        'child_routes' => [
                            'default' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:action',
                                    'constraints' => [
                                        'action' => 'add|browse|edit|show|index|batch-delete-all|batch-delete-confirm|batch-delete|reindex',
                                    ],
                                    'defaults' => [
                                        'action' => 'browse',
                                    ],
                                ],
                            ],
                            'id' => [
                                'type' => \Laminas\Router\Http\Segment::class,
                                'options' => [
                                    'route' => '/:language[/:action]',
                                    'constraints' => [
                                        'action' => '[a-zA-Z][a-zA-Z0-9_-]*',
                                        // The language tag follows the BCP47 specification
                                        // according to the list of languages supported by Omeka.
                                        // The recommandation allows 7 subtags of 1 to 8 characters
                                        // separated with a "-". Each subtag should be listed in
                                        // the recommandation. The case should follow the
                                        // recommandation.
                                        // The separator should be a "-", but laminas uses "_".
                                        /** @see https://en.wikipedia.org/wiki/IETF_language_tag */
                                        // 'locale' => '[a-zA-Z]{1,8}((-|_)[a-zA-Z0-9]{1,8}){0,6}',
                                        // This is a locale for pages, not resources, where another pattern is used.
                                        // See application/asset/js/global.js.
                                        'language' => '[a-zA-Z]{2,3}((-|_)[a-zA-Z0-9]{2,4})?',
                                    ],
                                    'defaults' => [
                                        'action' => 'show',
                                    ],
                                ],
                            ],
                        ]
                    ],
                ],
            ],
        ],
    ],
    'navigation' => [
        'AdminModule' => [
            'translation' => [
                'label' => 'Translations', // @translate
                'route' => 'admin/translation',
                'resource' => Controller\Admin\TranslationController::class,
                'privilege' => 'browse',
                // 'class' => 'o-icon- fa-globe',
                'class' => 'o-icon- fa-language',
                'pages' => [
                    [
                        'route' => 'admin/translation/default',
                        'visible' => false,
                    ],
                    [
                        'route' => 'admin/translation/id',
                        'visible' => false,
                    ],
                ],
            ],
        ],
    ],
    /**
     * To manage translation directly from the database, see:
     * @see https://docs.laminas.dev/laminas-i18n/translator/factory
     * @see https://stackoverflow.com/questions/44406391/zend-framework-3-translator-from-db#answer-44413709
     * But it will be slower than using prepared files.
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
            // Translations of the module itself.
            [
                'type' => 'gettext',
                'base_dir' => dirname(__DIR__) . '/language',
                'pattern' => '%s.mo',
                'text_domain' => null,
            ],
            // Translations files are automatically updated by this module.
            [
                'type' => \Laminas\I18n\Translator\Loader\PhpArray::class,
                'base_dir' => $localPath . '/language',
                'pattern' => '%s.php',
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
