<?php declare(strict_types=1);

namespace Internationalisation;

if (!class_exists('Common\TraitModule', false)) {
    require_once dirname(__DIR__) . '/Common/TraitModule.php';
}

use Common\Stdlib\PsrMessage;
use Common\TraitModule;
use Internationalisation\Api\Representation\SitePageRelationRepresentation;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Mvc\MvcEvent;
use Omeka\Module\AbstractModule;

/**
 * Internationalisation.
 *
 * @copyright Daniel Berthereau, 2019-2026
 * @copyright See commits.
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 */
class Module extends AbstractModule
{
    use TraitModule;

    const NAMESPACE = __NAMESPACE__;

    /**
     * @var array
     */
    protected $cacheLocaleValues = [];

    /**
     * Sort order of the last select for vocabulary members query.
     *
     * @var array
     */
    protected $lastQuerySort = [];

    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);

        /** @var \Omeka\Permissions\Acl $acl */
        $acl = $this->getServiceLocator()->get('Omeka\Acl');

        // Only some roles can manage translations, that are designed for sites.
        // But it is complex to manage site roles here, so use all default roles.
        $defaultRoles = [
            \Omeka\Permissions\Acl::ROLE_GLOBAL_ADMIN,
            \Omeka\Permissions\Acl::ROLE_SITE_ADMIN,
            \Omeka\Permissions\Acl::ROLE_EDITOR,
            \Omeka\Permissions\Acl::ROLE_REVIEWER,
            \Omeka\Permissions\Acl::ROLE_AUTHOR,
            \Omeka\Permissions\Acl::ROLE_RESEARCHER,
        ];

        $acl
            ->allow(
                null,
                [\Internationalisation\Api\Adapter\SitePageRelationAdapter::class],
                ['search', 'read']
            );

        $acl
            // Anybody can search and read translations (mainly via api endpoint).
            ->allow(
                null,
                [
                    \Internationalisation\Api\Adapter\TranslatedAdapter::class,
                ],
                [
                    'search',
                    'read',
                ]
            )
            ->allow(
                null,
                [
                    \Internationalisation\Entity\Translated::class,
                ],
                [
                    'read',
                ]
            )

            // Admin part.
            ->allow(
                $defaultRoles,
                [
                    \Internationalisation\Controller\Admin\TranslationController::class,
                    \Internationalisation\Api\Adapter\TranslatedAdapter::class,
                    \Internationalisation\Entity\Translated::class,
                ]
            )
        ;

        $this->attachTranslatorFallback();
    }

    /**
     * Wire a fallback locale chain on the MvcTranslator missing-translation
     * event. Reads site setting `internationalisation_fallbacks` (list of
     * locales). For each $translate() call whose key is absent in the current
     * locale, the listener retries the lookup against each fallback locale in
     * order and returns the first hit. Re-entry guard prevents recursion when
     * the fallback locale itself misses the key.
     */
    protected function attachTranslatorFallback(): void
    {
        $services = $this->getServiceLocator();
        $status = $services->get('Omeka\Status');
        if (!$status->isSiteRequest()) {
            return;
        }

        $siteSettings = $services->get('Omeka\Settings\Site');
        try {
            $fallbacks = $siteSettings->get('internationalisation_fallbacks', []);
        } catch (\Throwable $e) {
            return;
        }
        $fallbacks = array_values(array_filter((array) $fallbacks, 'strlen'));
        if (!$fallbacks) {
            return;
        }

        $translator = $services->get('MvcTranslator')->getDelegatedTranslator();
        $translator->enableEventManager();
        $translator->getEventManager()->attach(
            \Laminas\I18n\Translator\Translator::EVENT_MISSING_TRANSLATION,
            function (\Laminas\EventManager\EventInterface $e) use ($translator, $fallbacks) {
                static $reentry = false;
                if ($reentry) {
                    return null;
                }
                $message = (string) $e->getParam('message');
                $textDomain = $e->getParam('text_domain') ?: 'default';
                $reentry = true;
                try {
                    foreach ($fallbacks as $locale) {
                        $translated = $translator->translate($message, $textDomain, $locale);
                        if ($translated !== $message) {
                            return $translated;
                        }
                    }
                } finally {
                    $reentry = false;
                }
                return null;
            }
        );
    }

    protected function preInstall(): void
    {
        $services = $this->getServiceLocator();
        $plugins = $services->get('ControllerPluginManager');
        $translator = $services->get('MvcTranslator');

        if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.86')) {
            $message = new \Omeka\Stdlib\Message(
                $translator->translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
                'Common', '3.4.86'
            );
            throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
        }

        $errors = [];

        $vendor = __DIR__ . '/vendor/daniel-km/simple-iso-639-3/src/Iso639p3.php';
        if (!file_exists($vendor)) {
            $errors[] = (string) (new PsrMessage(
                'The composer vendor is not ready. See module’s installation documentation.' // @translate
            ))->setTranslator($translator);
        }

        $this->checkExtensionIntl();

        $config = $services->get('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');

        if (!$this->checkDestinationDir($basePath . '/internationalisation')) {
            $errors[] = (string) new PsrMessage(
                'The directory "{path}" is not writeable.', // @translate
                ['path' => $basePath . '/internationalisation']
            );
        }

        if ($errors) {
            throw new \Omeka\Module\Exception\ModuleCannotInstallException(implode("\n", $errors));
        }
    }

    protected function postInstall(): void
    {
        $this->recommendSiteHub();
    }

    /**
     * Recommend the module Site Hub to manage site and theme settings of a
     * group of sites more easily. No-op when it is already available.
     */
    protected function recommendSiteHub(): void
    {
        if (class_exists('SiteHub\Module', false)) {
            return;
        }
        $services = $this->getServiceLocator();
        $messenger = $services->get('ControllerPluginManager')->get('messenger');
        $messenger->addWarning(new PsrMessage(
            'To manage the site settings and theme settings of grouped sites more easily, it is recommended to install the module Site Hub, that propagates settings between the sites of a group.' // @translate
        ));
    }

    protected function checkExtensionIntl(): void
    {
        if (!extension_loaded('intl')) {
            /** @var \Omeka\Mvc\Controller\Plugin\Messenger $messenger */
            $services = $this->getServiceLocator();
            $messenger = $services->get('ControllerPluginManager')->get('messenger');
            $messenger->addWarning(
                'The php extension "intl" is not available. It is recommended to install it to manage diacritics and non-latin characters and to translate dates, numbers and more.' // @translate
            );
        }
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        // TODO Find a better event or identifier to add css/js in public side.
        $sharedEventManager->attach(
            '*',
            'view.layout',
            [$this, 'handleViewLayoutPublic']
        );

        // Handle translated title.
        $sharedEventManager->attach(
            \Omeka\Api\Representation\ItemRepresentation::class,
            'rep.resource.title',
            [$this, 'handleResourceTitle']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Representation\ItemSetRepresentation::class,
            'rep.resource.title',
            [$this, 'handleResourceTitle']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Representation\MediaRepresentation::class,
            'rep.resource.title',
            [$this, 'handleResourceTitle']
        );
        $sharedEventManager->attach(
            \Annotate\Api\Representation\AnnotationRepresentation::class,
            'rep.resource.title',
            [$this, 'handleResourceTitle']
        );

        // Handle order of values according to settings.
        $sharedEventManager->attach(
            \Omeka\Api\Representation\ItemRepresentation::class,
            'rep.resource.values',
            [$this, 'handleResourceValues']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Representation\ItemSetRepresentation::class,
            'rep.resource.values',
            [$this, 'handleResourceValues']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Representation\MediaRepresentation::class,
            'rep.resource.values',
            [$this, 'handleResourceValues']
        );
        $sharedEventManager->attach(
            \Annotate\Api\Representation\AnnotationRepresentation::class,
            'rep.resource.values',
            [$this, 'handleResourceValues']
        );

        // Handle filter of values according to settings.
        $sharedEventManager->attach(
            \Omeka\Api\Representation\ItemRepresentation::class,
            'rep.resource.display_values',
            [$this, 'handleResourceDisplayValues']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Representation\ItemSetRepresentation::class,
            'rep.resource.display_values',
            [$this, 'handleResourceDisplayValues']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Representation\MediaRepresentation::class,
            'rep.resource.display_values',
            [$this, 'handleResourceDisplayValues']
        );
        $sharedEventManager->attach(
            \Annotate\Api\Representation\AnnotationRepresentation::class,
            'rep.resource.display_values',
            [$this, 'handleResourceDisplayValues']
        );

        // Manage the translation of the property labels.
        $sharedEventManager->attach(
            \Omeka\Api\Representation\ItemRepresentation::class,
            'rep.resource.json',
            [$this, 'filterJsonLdResource']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Representation\ItemSetRepresentation::class,
            'rep.resource.json',
            [$this, 'filterJsonLdResource']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Representation\MediaRepresentation::class,
            'rep.resource.json',
            [$this, 'filterJsonLdResource']
        );
        $sharedEventManager->attach(
            \Annotate\Api\Representation\AnnotationRepresentation::class,
            'rep.resource.json',
            [$this, 'filterJsonLdResource']
        );

        // Add the related pages to the representation of the pages.
        $sharedEventManager->attach(
            \Omeka\Api\Representation\SitePageRepresentation::class,
            'rep.resource.json',
            [$this, 'filterJsonLdSitePage']
        );

        // Store the related pages when the page is saved.
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\SitePageAdapter::class,
            'api.update.post',
            [$this, 'handleApiUpdatePostPage']
        );

        // Order the form element for properties and resource classes.
        $sharedEventManager->attach(
            \Omeka\Form\Element\AbstractVocabularyMemberSelect::class,
            'form.vocab_member_select.query',
            [$this, 'filterVocabularyMemberSelectQuery']
        );
        $sharedEventManager->attach(
            \Omeka\Form\Element\AbstractVocabularyMemberSelect::class,
            'form.vocab_member_select.value_options',
            [$this, 'filterVocabularyMemberSelectValues']
        );

        // As long as the core SitePageForm has no event, all derivative forms
        // should be set.
        $sharedEventManager->attach(
            \Omeka\Form\SitePageForm::class,
            'form.add_elements',
            [$this, 'handleSitePageForm']
        );
        $sharedEventManager->attach(
            \BlockPlus\Form\SitePageForm::class,
            'form.add_elements',
            [$this, 'handleSitePageForm']
        );
        $sharedEventManager->attach(
            \Internationalisation\Form\SitePageForm::class,
            'form.add_elements',
            [$this, 'handleSitePageForm']
        );

        // Settings.
        $sharedEventManager->attach(
            \Omeka\Form\SettingForm::class,
            'form.add_elements',
            [$this, 'handleMainSettings']
        );
        $sharedEventManager->attach(
            \Omeka\Form\SettingForm::class,
            'form.add_input_filters',
            [$this, 'handleMainSettingsFilters']
        );
        $sharedEventManager->attach(
            \Omeka\Form\SiteSettingsForm::class,
            'form.add_elements',
            [$this, 'handleSiteSettings']
        );

        // Duplicate site.
        $sharedEventManager->attach(
            \Omeka\Form\SiteForm::class,
            'form.add_elements',
            [$this, 'handleSiteFormElements']
        );
        $sharedEventManager->attach(
            \Omeka\Form\SiteForm::class,
            'form.add_input_filters',
            [$this, 'handleSiteFormFilters']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\SiteAdapter::class,
            'api.create.post',
            [$this, 'handleSitePost']
        );
        $sharedEventManager->attach(
            \Omeka\Api\Adapter\SiteAdapter::class,
            'api.update.post',
            [$this, 'handleSitePost']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\SiteAdmin\Index',
            'view.add.after',
            [$this, 'handleSiteAdminViewAfter']
        );
        $sharedEventManager->attach(
            'Omeka\Controller\SiteAdmin\Index',
            'view.edit.after',
            [$this, 'handleSiteAdminViewAfter']
        );
    }

    public function handleViewLayoutPublic(Event $event): void
    {
        $view = $event->getTarget();
        if (!$view->status()->isSiteRequest()) {
            return;
        }

        $assetUrl = $view->getHelperPluginManager()->get('assetUrl');
        $view->headLink()
            ->appendStylesheet($assetUrl('css/language-switcher.css', 'Internationalisation'))
            ->appendStylesheet($assetUrl('vendor/flag-icon-css/css/flag-icon.min.css', 'Internationalisation'));
    }

    /**
     * Manage internationalisation of the title.
     *
     * @param Event $event
     */
    public function handleResourceTitle(Event $event): void
    {
        $locales = $this->getLocales();
        if (!$locales) {
            return;
        }

        // When we want a translated title, we don’t care of the existing title.
        // Just get the title via value(), that takes care of the language.
        // Similar logic can be found in \Omeka\Api\Representation\AbstractResourceEntityRepresentation::displayDescription()

        /**
         * @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource
         */
        $resource = $event->getTarget();
        $template = $resource->resourceTemplate();
        if ($template && $property = $template->titleProperty()) {
            $title = $resource->value($property->term())
                ?? $resource->value('dcterms:title');
        } else {
            $title = $resource->value('dcterms:title');
        }

        if (!$title) {
            return;
        }

        $event->setParam('title', (string) $title);
    }

    /**
     * Order values of each property according to settings, without filtering.
     *
     * All values in all languages are cached internally for each resource. The
     * first value is always in the good locale, in particular for title. The
     * other values are displayed and filtered via method displayValues().
     *
     * @todo Improve this process for memory and to avoid to loop values (even if it's not the common case). Store only language+key order of the value?
     *
     * @param Event $event
     */
    public function handleResourceValues(Event $event): void
    {
        $locales = $this->getLocales();
        if (!$locales) {
            return;
        }

        $resourceId = $event->getTarget()->id();
        if (isset($this->cacheLocaleValues[$resourceId])) {
            $values = $event->getParam('values');
            foreach ($this->cacheLocaleValues[$resourceId] as $term => $valuesByLang) {
                // Flatten the values grouped by language into a single array.
                // Note: array_merge(...array_values($valuesByLang)) fails when
                // $valuesByLang is empty (no arguments to array_merge before
                // PHP 7.4).
                // $values[$term]['values'] = array_merge(...array_values($valuesByLang));
                $vv = [];
                foreach ($valuesByLang as $vvalues) {
                    $vv = array_merge($vv, array_values($vvalues));
                }
                $values[$term]['values'] = $vv;
            }
            $event->setParam('values', $values);
            return;
        }

        $this->cacheLocaleValues[$resourceId] = [];

        // Order values for each property according to settings.
        $values = $event->getParam('values');
        foreach ($values as $term => &$valueInfo) {
            // The key "values" can be null when a property is defined in a
            // template but has no values for the resource.
            if ($valueInfo['values']) {
                $valuesByLang = $locales;
                foreach ($valueInfo['values'] as $value) {
                    $valuesByLang[$value->lang()][] = $value;
                }
                $valuesByLang = array_filter($valuesByLang) ?: [];
            } else {
                $valuesByLang = [];
            }
            $this->cacheLocaleValues[$resourceId][$term] = $valuesByLang;
            // Flatten the values grouped by language into a single array.
            $vv = [];
            foreach ($valuesByLang as $vvalues) {
                $vv = array_merge($vv, array_values($vvalues));
            }
            $valueInfo['values'] = $vv;
        }
        unset($valueInfo);

        $event->setParam('values', $values);
    }

    /**
     * Filter values of each property according to settings.
     *
     * Note: the values are already ordered by language in previous event.
     *
     * @param Event $event
     */
    public function handleResourceDisplayValues(Event $event): void
    {
        $locales = $this->getLocales();
        if (!$locales) {
            return;
        }

        $services = $this->getServiceLocator();

        /** @var \Omeka\Settings\SiteSettings $siteSettings */
        $siteSettings = $services->get('Omeka\Settings\Site');

        $displayValues = $siteSettings->get('internationalisation_display_values', 'all');
        if (in_array($displayValues, ['all', 'all_site', 'all_iso', 'all_fallback'])) {
            return;
        }

        $resourceId = $event->getTarget()->id();

        // $fallbacks = $siteSettings->get('internationalisation_fallbacks', []);
        $requiredLanguages = $siteSettings->get('internationalisation_required_languages', []);

        // Filter appropriate locales for each property when it is localisable.
        $values = $event->getParam('values');
        foreach ($values as $term => &$valueInfo) {
            $valuesByLang = $this->cacheLocaleValues[$resourceId][$term] ?? [];

            // Check if the property has at least one language (not identifier,
            // etc.).
            if (!count($valuesByLang)
                || (count($valuesByLang) === 1 && isset($valuesByLang['']))
            ) {
                continue;
            }

            switch ($displayValues) {
                case 'site':
                case 'site_iso':
                    $vals = array_intersect_key($valuesByLang, $locales);
                    $valueInfo['values'] = $vals
                        ? array_merge(...array_values($vals))
                        : [];
                    break;

                case 'site_fallback':
                    // Keep only values with fallbacks and take only the first
                    // non empty and the required ones.
                    $vals = array_intersect_key($valuesByLang, $locales);
                    if ($vals) {
                        $vals = array_slice($vals, 0, 1, true);
                    }
                    $vals += array_intersect_key($valuesByLang, $requiredLanguages);
                    $valueInfo['values'] = $vals
                        ? array_merge(...array_values($vals))
                        : [];
                    break;

                default:
                    return;
            }
        }
        unset($valueInfo);

        $event->setParam('values', $values);
    }

    /**
     * Translate the property labels according to the locale set in the query.
     *
     * The aim of this filter is to simplify the processes of external clients.
     * It applies only for the external api requests. It allows to get the
     * translated label of the property, or the translated label of the resource
     * template, if any, without another request and a new process.
     *
     * @todo Add another argument "use_locale_first" to reorder the values according to the locale.
     * @todo Use the headers, so the client doesn't modify the query (but it is already modified for the authentification).
     *
     * @param Event $event
     */
    public function filterJsonLdResource(Event $event): void
    {
        // TODO Use the Zend cache.
        /** @var \Laminas\Mvc\I18n\Translator $translator */
        static $translator;
        static $propertyLabels;
        static $templatePropertyLabels = [[]];
        static $resourceClassLabels = [];
        static $resourceTemplateLabels = [];

        $services = $this->getServiceLocator();

        // Process only external api requests.
        /** @var \Laminas\Mvc\MvcEvent $mvcEvent */
        $mvcEvent = $services->get('Application')->getMvcEvent();
        // A check is required on route match to allow background processes.
        $routeMatch = $mvcEvent->getRouteMatch();
        // To make
        if (!$routeMatch || !$routeMatch->getParam('__API__')) {
            return;
        }

        /** @var \Laminas\Http\Request $request */
        $request = $mvcEvent->getRequest();

        // Use "use_locale" instead of "locale" to avoid conflicts with some
        // possible future api requests.
        $locale = $request->getQuery()->get('use_locale');
        $useTemplateLabel = (bool) $request->getQuery()->get('use_template_label');
        if (!$locale && !$useTemplateLabel) {
            return;
        }

        /**
         * @var \Omeka\Api\Representation\AbstractResourceEntityRepresentation $resource
         * @var array $jsonLd
         */
        $resource = $event->getTarget();
        $jsonLd = $event->getParam('jsonLd');

        // Prepare the translator in all cases.
        if ($propertyLabels === null) {
            $propertyLabels = [];
            $translator = $services->get('MvcTranslator');
        }

        // Set the default locale for intl functions only when a locale is
        // requested, then restore the original locale to avoid side effects.
        $originalLocale = null;
        if ($locale && extension_loaded('intl')) {
            $originalLocale = \Locale::getDefault();
            \Locale::setDefault($locale);
        }

        // Set the locale.
        if ($locale) {
            $translator->getDelegatedTranslator()->setLocale($locale);
        }

        // Prepare the template labels.
        $templateId = 0;
        if ($useTemplateLabel) {
            // Resource class and template labels are added too for simplicity.
            $class = $resource->resourceClass();
            if ($class) {
                $classId = $class->id();
                if (!isset($resourceClassLabels[$classId])) {
                    $resourceClassLabels[$classId] = $locale
                        ? $translator->translate($class->label())
                        : $class->label();
                }
                // Manage Omeka < 4.2.
                if (is_object($jsonLd['o:resource_class'])) {
                    $jsonLd['o:resource_class'] = $jsonLd['o:resource_class']->jsonSerialize();
                }
                $jsonLd['o:resource_class']['o:label'] = $resourceClassLabels[$classId];
            }

            $template = $resource->resourceTemplate();
            if ($template) {
                $templateId = $template->id();
                if (!isset($templatePropertyLabels[$templateId])) {
                    $resourceTemplateLabels[$templateId] = $locale
                        ? $translator->translate($template->label())
                        : $template->label();
                    foreach ($template->resourceTemplateProperties() as $templateProperty) {
                        $label = $templateProperty->alternateLabel();
                        if (strlen($label)) {
                            $templatePropertyLabels[$templateId][$templateProperty->property()->id()] = $locale
                                ? $translator->translate($label)
                                : $label;
                        }
                    }
                }
                if (is_object($jsonLd['o:resource_template'])) {
                    $jsonLd['o:resource_template'] = $jsonLd['o:resource_template']->jsonSerialize();
                }
                $jsonLd['o:resource_template']['o:label'] = $resourceTemplateLabels[$templateId];
            } elseif (!$locale) {
                return;
            }
        }

        if ($useTemplateLabel && $templateId) {
            // Process the replacement of the property labels, with or without
            // locale.
            foreach (array_keys($resource->values()) as $term) {
                foreach ($jsonLd[$term] as &$value) {
                    $value = json_decode(json_encode($value), true);
                    $propertyId = $value['property_id'];
                    $label = $value['property_label'];
                    if (isset($templatePropertyLabels[$templateId][$propertyId])) {
                        $value['property_label'] = $templatePropertyLabels[$templateId][$propertyId];
                    } else {
                        if (!isset($propertyLabels[$label])) {
                            $propertyLabels[$label] = $translator->translate($label);
                        }
                        $value['property_label'] = $propertyLabels[$label];
                    }
                }
                unset($value);
            }
        } else {
            // Process the replacement of the property labels without template.
            foreach (array_keys($resource->values()) as $term) {
                foreach ($jsonLd[$term] as &$value) {
                    // In most of the cases in real data, there is only one value by
                    // property, so it's useless to store the label outside of the
                    // loop, that requires a json conversion or to get the value
                    // representation.
                    $value = json_decode(json_encode($value), true);
                    $label = $value['property_label'];
                    if (!isset($propertyLabels[$label])) {
                        $propertyLabels[$label] = $translator->translate($label);
                    }
                    $value['property_label'] = $propertyLabels[$label];
                }
                unset($value);
            }
        }

        // Restore the original locale if it was changed.
        if ($originalLocale !== null) {
            \Locale::setDefault($originalLocale);
        }

        $event->setParam('jsonLd', $jsonLd);
    }

    public function filterJsonLdSitePage(Event $event): void
    {
        /**
         * The related pages are json-serialized so the main page will be full
         * json.
         *
         * @var \Omeka\Api\Representation\SitePageRepresentation $page
         * @var \Internationalisation\Api\Representation\SitePageRelationRepresentation[] $relations
         */
        $page = $event->getTarget();
        $jsonLd = $event->getParam('jsonLd');
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');
        $pageId = $page->id();
        $relations = $api->search('site_page_relations', ['relation' => $pageId])->getContent();
        $relations = array_map(function (SitePageRelationRepresentation $relation) use ($pageId) {
            $related = $relation->relatedPage();
            $relatedPage = $pageId === $related->id()
                ? $relation->page()->getReference()->jsonSerialize()
                : $related->getReference()->jsonSerialize();
            return $relatedPage;
        }, $relations);
        $jsonLd['o-module-internationalisation:related_page'] = $relations;
        $event->setParam('jsonLd', $jsonLd);
    }

    public function handleApiUpdatePostPage(Event $event): void
    {
        $services = $this->getServiceLocator();
        /**
         * @var \Doctrine\DBAL\Connection $connection
         * @var \Omeka\Api\Manager $api
         * @var \Omeka\Api\Request $request
         */
        $connection = $services->get('Omeka\Connection');
        $api = $services->get('Omeka\ApiManager');
        $request = $event->getParam('request');
        $response = $event->getParam('response');
        $pageId = $response->getContent()->getId();

        $selected = $request->getValue('o-module-internationalisation:related_page', []);
        $selected = array_map('intval', $selected);

        // The page cannot be related to itself.
        $key = array_search($pageId, $selected);
        if ($key !== false) {
            unset($selected[$key]);
        }

        // To simplify process, all existing pairs are deleted before saving.

        // Direct query is used because the visibility don't need to be checked:
        // it is done when the page is loaded and even hidden, the relation
        // should remain.
        // TODO Check if this process remove hidden pages in true life (with language switcher, the user should see all localized sites).

        // Delete only relations involving the current page, not relations
        // between other related pages.
        $sql = <<<SQL
            DELETE FROM site_page_relation
            WHERE page_id = :page_id OR related_page_id = :page_id
            SQL;
        $connection->executeQuery($sql, ['page_id' => $pageId], ['page_id' => \Doctrine\DBAL\ParameterType::INTEGER]);

        if (empty($selected)) {
            return;
        }

        // Add all pairs using parameterized query.
        $ids = $selected;
        $ids[] = $pageId;
        sort($ids);
        $relatedIds = $ids;

        $values = [];
        $params = [];
        $types = [];
        $i = 0;
        foreach ($ids as $id) {
            foreach ($relatedIds as $relatedId) {
                if ($relatedId > $id) {
                    $values[] = "(:p{$i}, :r{$i})";
                    $params["p{$i}"] = $id;
                    $params["r{$i}"] = $relatedId;
                    $types["p{$i}"] = \Doctrine\DBAL\ParameterType::INTEGER;
                    $types["r{$i}"] = \Doctrine\DBAL\ParameterType::INTEGER;
                    ++$i;
                }
            }
        }

        if (empty($values)) {
            return;
        }

        $sql = 'INSERT INTO site_page_relation (page_id, related_page_id) VALUES '
            . implode(', ', $values)
            . ' ON DUPLICATE KEY UPDATE `id` = `id`';

        $connection->executeStatement($sql, $params, $types);
    }

    public function filterVocabularyMemberSelectQuery(Event $event): void
    {
        $query = $event->getParam('query', []);
        $this->lastQuerySort = [
            'sort_by' => $query['sort_by'] ?? null,
            'sort_order' => isset($query['sort_order']) && strtolower((string) $query['sort_order']) === 'desc' ? 'desc' : 'asc',
        ];
    }

    public function filterVocabularyMemberSelectValues(Event $event): void
    {
        if (($this->lastQuerySort['sort_by'] ?? null) !== 'label') {
            $this->lastQuerySort = [];
            return;
        }

        // TODO Check for last upgrade of Omeka S.

        // TODO Replace this event by a upper level sql event. May require insertion of translated terms in a table (automatically via rdf or po files?).

        $valueOptions = $event->getParam('valueOptions', []);

        // During this event, the labels are not yet translated by Zend form.
        // They must not be translated twice.
        $translator = $this->getServiceLocator()->get('MvcTranslator');

        // Use Collator for locale-aware sorting (accented letters).
        // Fallback to natcasesort() when intl extension is not available.
        $locale = extension_loaded('intl') ? \Locale::getDefault() : null;
        $collator = $locale ? new \Collator($locale) : null;
        $localeSort = function (&$array) use ($collator): void {
            if ($collator) {
                $collator->asort($array, \Collator::SORT_STRING);
            } else {
                natcasesort($array);
            }
        };

        // Order first level by translated label: don't order prepended values,
        // dcterms and dctype.
        // The prepended values may contain array (module BulkImport).
        // Keys "dcterms" and "dctype" may be missing (no example currently, but
        // dctype is not used for properties).
        if (isset($valueOptions['dcterms'])) {
            $offset = array_search('dcterms', array_keys($valueOptions));
            $prepended = array_slice($valueOptions, 0, $offset + 1, true);
            $appended = array_slice($valueOptions, $offset + 1, null, true);
        } elseif (isset($valueOptions['dctype'])) {
            $offset = array_search('dctype', array_keys($valueOptions));
            $prepended = array_slice($valueOptions, 0, $offset + 1, true);
            $appended = array_slice($valueOptions, $offset + 1, null, true);
        } else {
            // This case is very rare (no example currently).
            // In most cases, prepended values are not arrays.
            $prepended = array_filter($valueOptions, 'is_scalar');
            $appended = array_diff_key($valueOptions, $prepended);
        }
        $translateLabels = fn ($v) => is_array($v) ? $translator->translate($v['label']) : $translator->translate($v);
        $appendedTranslated = array_map($translateLabels, $appended);
        $localeSort($appendedTranslated);
        $appended = array_replace($appendedTranslated, $appended);
        $valueOptions = $prepended + $appended;

        // Order second level by translated label, dcterms / dctype included,
        // but not the prepended values.
        if (isset($valueOptions['dctype'])) {
            $offset = array_search('dctype', array_keys($valueOptions));
            $prepended = array_slice($valueOptions, 0, $offset, true);
            $appended = array_slice($valueOptions, $offset, null, true);
        } elseif (isset($valueOptions['dcterms'])) {
            $offset = array_search('dcterms', array_keys($valueOptions));
            $prepended = array_slice($valueOptions, 0, $offset, true);
            $appended = array_slice($valueOptions, $offset, null, true);
        } else {
            $prepended = array_filter($valueOptions, 'is_scalar');
            $appended = array_diff_key($valueOptions, $prepended);
        }
        $reverted = $this->lastQuerySort['sort_order'] === 'desc';
        $translateOptionsLabels = function ($v) use ($translator, $reverted, $localeSort) {
            if (is_scalar($v) || empty($v['options'])) {
                return $v;
            }
            $optionLabelsTranslated = array_map(fn ($vv) => $translator->translate($vv['label']), $v['options']);
            $localeSort($optionLabelsTranslated);
            if ($reverted) {
                $optionLabelsTranslated = array_reverse($optionLabelsTranslated, true);
            }
            $v['options'] = array_replace($optionLabelsTranslated, $v['options']);
            return $v;
        };
        $appended = array_map($translateOptionsLabels, $appended);
        $valueOptions = $prepended + $appended;

        $this->lastQuerySort = [];
        $event->setParam('valueOptions', $valueOptions);
    }

    public function handleMainSettings(Event $event): void
    {
        // Process parent settings.
        $this->handleAnySettings($event, 'settings');

        $services = $this->getServiceLocator();

        $api = $services->get('Omeka\ApiManager');
        $sites = $api
            ->search('sites', ['sort_by' => 'slug', 'sort_order' => 'asc'], ['returnScalar' => 'slug'])
            ->getContent();

        $listSiteGroups = $services->get('ControllerPluginManager')->get('listSiteGroups');

        $siteGroups = $listSiteGroups();
        $siteGroupsString = '';
        foreach ($siteGroups as $group) {
            if ($group) {
                $siteGroupsString .= implode(' ', $group) . "\n";
            }
        }
        $siteGroupsString = trim($siteGroupsString);

        /**
         * @var \Omeka\Form\Element\RestoreTextarea $siteGroupsElement
         * @var \Internationalisation\Form\SettingsFieldset $fieldset
         */
        $form = $event->getTarget();
        $fieldset = $form;
        $siteGroupsElement = $fieldset
            ->get('internationalisation_site_groups');
        $siteGroupsElement
            ->setValue($siteGroupsString)
            ->setRestoreButtonText('Remove all groups') // @translate
            ->setRestoreValue(implode("\n", $sites));
    }

    public function handleMainSettingsFilters(Event $event): void
    {
        $inputFilter = $event->getParam('inputFilter');
        $inputFilter
            ->add([
                'name' => 'internationalisation_site_groups',
                'required' => false,
                'filters' => [
                    [
                        'name' => \Laminas\Filter\Callback::class,
                        'options' => [
                            'callback' => [$this, 'filterSiteGroups'],
                        ],
                    ],
                ],
            ])
            ->add([
                'name' => 'internationalisation_extra_locales',
                'required' => false,
            ]);
    }

    public function handleSiteSettings(Event $event): void
    {
        $this->handleAnySettings($event, 'site_settings');
        $this->prepareSiteLocales();

        // Insist that even an English site must set its locale to "en": when
        // the locale is empty, the interface strings fall back to the global
        // default locale, so a site meant to be English may display strings
        // translated into another language.
        $services = $this->getServiceLocator();

        $siteSettings = $services->get('Omeka\Settings\Site');
        if (!$siteSettings->get('locale')) {
            $messenger = $services->get('ControllerPluginManager')->get('messenger');
            $messenger->addWarning(new PsrMessage(
                'No locale is set for this site: interface strings fall back to the global default locale and may appear in another language. Set the locale below (use "en" for an English site).' // @translate
            ));
        }
    }

    public function handleSitePageForm(Event $event): void
    {
        /** @var \Laminas\Form\Form $form */
        $form = $event->getTarget();

        // The select for page models is added only on an existing page.
        if ($form->getOption('addPage')) {
            return;
        }

        // TODO Display one select by translated site.

        $form
            ->add([
                'name' => 'o-module-internationalisation:related_page',
                'type' => \Common\Form\Element\SitesPageSelect::class,
                'options' => [
                    'label' => 'Translations', // @translate
                    'info' => 'The selected pages will be translations of the current page within a site group, that must be defined. The language switcher displays only one related page by site.', // @translate
                    'site_group' => 'internationalisation_site_groups',
                    'exclude_current_site' => true,
                    'site_label' => 'title_slug',
                    'page_label' => 'slug',
                ],
                'attributes' => [
                    'id' => 'o-module-internationalisation:related_page',
                    'required' => false,
                    'multiple' => true,
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select translations of this page…', // @translate
                ],
            ]);
    }

    public function handleSiteFormElements(Event $event): void
    {
        /**
         * @var \Laminas\Router\Http\RouteMatch $routeMatch
         * @var \Internationalisation\Form\DuplicateSiteFieldset $fieldset
         */
        $services = $this->getServiceLocator();
        $routeMatch = $services->get('Omeka\Status')->getRouteMatch();
        $isNew = $routeMatch->getParam('controller') === 'Omeka\Controller\SiteAdmin\Index'
            && $routeMatch->getParam('action') === 'add';
        $fieldset = $services->get('FormElementManager')->get(
            \Internationalisation\Form\DuplicateSiteFieldset::class,
            [
                'is_new' => $isNew,
                'collecting' => $this->isModuleActive('Collecting'),
            ]
        );
        $event->getTarget()->add($fieldset);
    }

    public function handleSiteFormFilters(Event $event): void
    {
        /**
         * @var \Internationalisation\Form\DuplicateSiteFieldset $fieldset
         */
        $inputFilter = $event->getParam('inputFilter')
            ->get('duplicate');
        $fieldset = $this->getServiceLocator()->get('FormElementManager')->get(
            \Internationalisation\Form\DuplicateSiteFieldset::class,
            [
                'is_new' => true,
                'collecting' => $this->isModuleActive('Collecting'),
            ]
        );
        $fieldset
            ->updateInputFilter($inputFilter);
    }

    public function handleSiteAdminViewAfter(Event $event): void
    {
        $view = $event->getTarget();
        $expand = json_encode($view->translate('Expand'), 320);
        $legend = json_encode($view->translate('Remove and copy data'), 320);
        echo <<<CSS
            <style>
            .collapse + #duplicate.collapsible {
                overflow: initial;
            }
            </style>
            <script type="text/javascript">
            $(document).ready(function() {
                $('[name^="duplicate"]').closest('.field')
                    .wrapAll('<fieldset id="duplicate" class="field-container collapsible">')
                    .closest('#duplicate')
                    .before('<a href="#" class="expand" aria-label=' + $expand + '>' + $legend + ' </a> ');
            });
            </script>
            CSS;
    }

    public function handleSitePost(Event $event): void
    {
        $site = $event->getParam('response')->getContent();
        if (empty($site)) {
            return;
        }

        /** @var \Omeka\Api\Request $request */
        $request = $event->getParam('request');
        $params = $request->getValue('duplicate', []);
        if (!count($params)) {
            return;
        }

        // Set default values in case of a creation outside of the form.
        $params += [
            'source' => null,
            'remove' => [],
            'copy' => [],
            'pages_mode' => null,
            'locale' => null,
            'is_new' => false,
        ];
        // TODO A source should be set even for remove currently.
        if (empty($params['remove'])) {
            $params['remove'] = [];
        }
        if (empty($params['copy'])) {
            $params['copy'] = [];
        }
        if ((!count($params['remove']) && !count($params['copy']))
            || (count($params['copy']) && !$params['source'])
        ) {
            return;
        }

        $services = $this->getServiceLocator();
        $messenger = $services->get('ControllerPluginManager')->get('messenger');
        $urlHelper = $services->get('ViewHelperManager')->get('url');

        try {
            $source = $params['source']
                ? $services->get('Omeka\ApiManager')->read('sites', ['id' => $params['source']], [], ['responseContent' => 'resource'])->getContent()
                : null;
        } catch (\Omeka\Api\Exception\NotFoundException $e) {
            $message = new PsrMessage(
                'The site #{site_id} cannot be copied. Check your rights.', // @translate
                ['site_id' => $params['source']]
            );
            $messenger->addError($message);
            return;
        }

        $isNew = (bool) $params['is_new'];
        if ($isNew) {
            $locale = $params['locale'];
        } else {
            $siteSettings = $services->get('Omeka\Settings\Site');
            $siteSettings->setTargetId($site->getId());
            $locale = $siteSettings->get('locale');
        }

        $args = [
            'target' => $site->getId(),
            'source' => $source ? $source->getId() : null,
            'remove' => $params['remove'],
            'copy' => $params['copy'],
            'pages_mode' => $params['pages_mode'],
            // Settings to keep.
            'settings' => ['locale' => $locale],
        ];

        // A sync job is used because it's a quick operation and rare.
        $strategy = $services->get(\Omeka\Job\DispatchStrategy\Synchronous::class);
        $job = $services->get(\Omeka\Job\Dispatcher::class)
            ->dispatch(\Internationalisation\Job\DuplicateSite::class, $args, $strategy);
        $message = new PsrMessage(
            'Remove/copy processes have been done for site "{site_slug}".', // @translate
            ['site_slug' => $site->getSlug()]
        );
        $messenger->addSuccess($message);

        $message = new PsrMessage(
            'A job was launched in background to copy site data: ({link_job}job #{job_id}{link_end}, {link_log}logs{link_end}).', // @translate
            [
                'link_job' => sprintf('<a href="%s">', htmlspecialchars($urlHelper('admin/id', ['controller' => 'job', 'id' => $job->getId()]))),
                'job_id' => $job->getId(),
                'link_end' => '</a>',
                'link_log' => class_exists('Log\Module', false)
                    ? sprintf('<a href="%1$s">', $urlHelper('admin/default', ['controller' => 'log'], ['query' => ['job_id' => $job->getId()]]))
                    : sprintf('<a href="%1$s" target="_blank">', $urlHelper('admin/id', ['controller' => 'job', 'action' => 'log', 'id' => $job->getId()])),
            ]
        );
        $message->setEscapeHtml(false);
        $messenger->addSuccess($message);
    }

    /**
     * For performance, save ordered locales and iso codes when needed.
     *
     * It's not possible to save it simply after validation, so add it here,
     * since the form is always reloaded after submission.
     */
    protected function prepareSiteLocales(): void
    {
        $siteSettings = $this->getServiceLocator()->get('Omeka\Settings\Site');

        $siteSettings->set('internationalisation_iso_codes', []);

        $locale = $siteSettings->get('locale');
        if (!$locale) {
            $siteSettings->set('internationalisation_locales', []);
            return;
        }

        $displayValues = $siteSettings->get('internationalisation_display_values', 'all');
        if ($displayValues === 'all') {
            $siteSettings->set('internationalisation_locales', []);
            return;
        }

        // Prepare the locales.
        $locales = [$locale];
        switch ($displayValues) {
            case 'all_site_iso':
            case 'site_iso':
                require_once __DIR__ . '/vendor/daniel-km/simple-iso-639-3/src/Iso639p3.php';
                $isoCodes = \Iso639p3\Iso639p3::codes($locale);
                $siteSettings->set('internationalisation_iso_codes', $isoCodes);
                $locales = array_merge($locales, $isoCodes);
                break;

            case 'all_fallback':
            case 'site_fallback':
                $locales = array_merge($locales, $siteSettings->get('internationalisation_fallbacks', []));
                break;

            case 'all_site':
            case 'site':
                // Nothing to do.
                break;

            default:
                $siteSettings->set('internationalisation_display_values', 'all');
                $siteSettings->set('internationalisation_locales', []);
                return;
        }

        $requiredLanguages = $siteSettings->get('internationalisation_required_languages', []);
        $locales = array_merge($locales, $requiredLanguages);
        $locales = array_fill_keys(array_unique(array_filter($locales)), []);

        // Add a fallback for values without language in all cases,
        // because in many cases default language is not set.
        // TODO Set an option to not fallback to values without language?
        $locales[''] = [];

        $siteSettings->set('internationalisation_locales', $locales);
    }

    public function filterSiteGroups($groups)
    {
        $services = $this->getServiceLocator();
        $api = $services->get('Omeka\ApiManager');

        $siteList = [];

        $sites = $api
            ->search('sites', ['sort_by' => 'slug', 'sort_order' => 'asc'], ['returnScalar' => 'slug'])
            ->getContent();
        $sites = array_combine($sites, $sites);

        $groups = $this->stringToList($groups);
        foreach ($groups as $group) {
            $group = array_unique(array_filter(array_map('trim', explode(' ', str_replace(',', ' ', $group)))));
            $group = array_intersect($group, $sites);
            if (count($group) > 1) {
                sort($group, SORT_NATURAL);
                foreach ($group as $site) {
                    $siteList[$site] = $group;
                    unset($sites[$site]);
                }
            }
        }

        ksort($siteList, SORT_NATURAL);
        return $siteList;
    }

    /**
     * List locales according to the request for a site.
     *
     * @fixme Remove the exception that occurs with background job and api during update: job seems to set status as site.
     *
     * Adapted:
     * @see \Internationalisation\Module::getLocales()
     * @see \Translator\Module::getLocaleCurrentSite()
     * @see \Translator\Module::getLanguagePairsOfSite()
     */
    protected function getLocales(): array
    {
        static $locales;

        if (is_array($locales)) {
            return $locales;
        }

        $locales = [];

        /**
         * @var \Omeka\Mvc\Status $status
         * @var \Omeka\Settings\SiteSettings $siteSettings
         * @var \Common\View\Helper\DefaultSite $defaultSite
         * @var \Omeka\Mvc\Controller\Plugin\CurrentSite $currentSite
         */
        $services = $this->getServiceLocator();
        $status = $services->get('Omeka\Status');

        // Currently limited to public front-end.
        // TODO In admin, use the user settings or add some main settings.
        if ($status->isSiteRequest()) {
            $siteSettings = $services->get('Omeka\Settings\Site');
            try {
                $locales = $siteSettings->get('internationalisation_locales', []);
            } catch (\Throwable $e) {
                // Probably a background process.
                // TODO Is the exception for current site fixed?
                $site = $services->get('ControllerPluginManager')->get('currentSite')()
                    ?: $services->get('ViewHelperManager')->get('defaultSite')();
                if ($site) {
                    $locales = $siteSettings->get('internationalisation_locales', [], $site->id());
                }
            }
        }

        return $locales;
    }

    /**
     * Get each line of a string separately.
     */
    protected function stringToList($string): array
    {
        return array_filter(array_map('trim', explode("\n", $this->fixEndOfLine($string))), 'strlen');
    }

    /**
     * Clean the text area from end of lines.
     *
     * This method fixes Windows and Apple copy/paste from a textarea input.
     */
    protected function fixEndOfLine($string): string
    {
        return strtr((string) $string, ["\r\n" => "\n", "\n\r" => "\n", "\r" => "\n"]);
    }
}
