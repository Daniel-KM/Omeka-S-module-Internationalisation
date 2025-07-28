<?php declare(strict_types=1);

namespace Internationalisation;

if (!class_exists('Common\TraitModule', false)) {
    require_once dirname(__DIR__) . '/Common/TraitModule.php';
}

use Common\Stdlib\PsrMessage;
use Common\TraitModule;
use Internationalisation\Api\Representation\SitePageRelationRepresentation;
use Internationalisation\Translator\Loader\PhpSimpleArray;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\I18n\Translator\TranslatorInterface;
use Laminas\Mvc\MvcEvent;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Module\AbstractModule;

/**
 * Internationalisation.
 *
 * @copyright Daniel Berthereau, 2019-2025
 * @copyright BibLibre, 2017
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

    public function getServiceConfig(): array
    {
        // The translator service for remote translator "tables" must be set,
        // whatever there are locales or not.
        // It avoids a second issue in case of error during bootstrap, in
        // particular duiring the upgrade of Common. It allows to finish the
        // upgrde.
        // The real content of the plugin is injected via MvcListeners.
        return [
            'factories' => [
                TranslatorInterface::class => function ($services) {
                    $translator = $services->get(TranslatorInterface::class)->getDelegatedTranslator();
                    $pluginManager = $translator->getPluginManager();
                    $pluginManager->setService(
                        'tables',
                        new PhpSimpleArray([])
                    );
                    return $translator;
                },
            ],
        ];
    }

    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);
        $this->addAclRules();
    }

    /**
     * Add ACL rules for this module.
     */
    protected function addAclRules(): void
    {
        /** @var \Omeka\Permissions\Acl $acl */
        $acl = $this->getServiceLocator()->get('Omeka\Acl');
        $acl
            ->allow(
                null,
                [\Internationalisation\Api\Adapter\SitePageRelationAdapter::class],
                ['search', 'read']
            );
    }

    protected function preInstall(): void
    {
        $services = $this->getServiceLocator();
        $plugins = $services->get('ControllerPluginManager');
        $translate = $plugins->get('translate');
        $translator = $services->get('MvcTranslator');

        if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.69')) {
            $message = new \Omeka\Stdlib\Message(
                $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
                'Common', '3.4.69'
            );
            throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
        }

        $vendor = __DIR__ . '/vendor/daniel-km/simple-iso-639-3/src/Iso639p3.php';
        if (!file_exists($vendor)) {
            $message = new PsrMessage(
                'The composer vendor is not ready. See module’s installation documentation.' // @translate
            );
            throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message->setTranslator($translator));
        }

        $this->checkExtensionIntl();
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
        $resource = $event->getTarget();
        $template = $resource->resourceTemplate();
        if ($template && $property = $template->titleProperty()) {
            $title = $resource->value($property->term());
            if ($title === null) {
                $title = $resource->value('dcterms:title');
            }
        } else {
            $title = $resource->value('dcterms:title');
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
                // TODO Sometime, array_merge of array_values returns a null.
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
            // Sometime, the key "values" is null.
            // TODO Find why the key "values" of the resource can be null. Probably related to templates.
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
            // TODO Sometime, array_merge of array_values returns a null.
            // $valueInfo['values'] = $valuesByLang ? array_merge(...array_values($valuesByLang)) : [];
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
            $valuesByLang = $this->cacheLocaleValues[$resourceId][$term];

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
        if (is_null($propertyLabels)) {
            $propertyLabels = [];
            if (extension_loaded('intl')) {
                \Locale::setDefault($locale);
            }
            $translator = $services->get('MvcTranslator');
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
                $jsonLd['o:resource_class'] = json_decode(json_encode($jsonLd['o:resource_class']), true);
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
                $jsonLd['o:resource_template'] = json_decode(json_encode($jsonLd['o:resource_template']), true);
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

        $event->setParam('jsonLd', $jsonLd);
    }

    protected function prepareTemplateLabels(AbstractResourceEntityRepresentation $resource, $locale)
    {
        $templatePropertyLabels = [];
        $template = $resource->resourceTemplate();
        // Prepare the template.
        $templateId = $template->id();
        if (!isset($template[$template->id()])) {
            foreach ($template->resourceTemplateProperties() as $templateProperty) {
                if ($label = $templateProperty->alternateLabel()) {
                    $templatePropertyLabels[$templateId][$templateProperty->property()->term()] = $label;
                }
            }
        }
        return $templatePropertyLabels;
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
        $relations = $api->search('site_page_relations',['relation' => $pageId])->getContent();
        $relations = array_map(function (SitePageRelationRepresentation $relation) use ($pageId) {
            $related = $relation->relatedPage();
            $relatedPage = $pageId === $related->id()
                ? $relation->page()->getReference()
                : $related->getReference();
            return $relatedPage->jsonSerialize();
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

        $existing = $api
            ->search(
                'site_page_relations',
                ['relation' => $pageId]
            )
            ->getContent();
        $existingIds = array_map(function ($relation) use ($pageId) {
            $relatedId = $relation->relatedPage()->id();
            return $pageId === $relatedId
                ? $relation->page()->id()
                : $relatedId;
        }, $existing);

        if (count($existingIds)) {
            $sql = <<<SQL
DELETE FROM site_page_relation
WHERE page_id IN (:page_ids) OR related_page_id IN (:page_ids)
SQL;
            $connection->executeQuery($sql, ['page_ids' => $existingIds], ['page_ids' => $connection::PARAM_INT_ARRAY]);
        }

        if (empty($selected)) {
            return;
        }

        // Add all pairs.
        $sql = <<<SQL
INSERT INTO site_page_relation (page_id, related_page_id)
VALUES
SQL;

        $ids = $selected;
        $ids[] = $pageId;
        sort($ids);
        $relatedIds = $ids;
        $has = false;
        foreach ($ids as $id) {
            foreach ($relatedIds as $relatedId) {
                if ($relatedId > $id) {
                    $has = true;
                    $sql .= "\n($id, $relatedId),";
                }
            }
        }
        $sql = rtrim($sql, ',');
        if (!$has) {
            return;
        }

        $sql .= ' ON DUPLICATE KEY UPDATE `id` = `id`;';

        $connection->executeStatement($sql);
    }

    public function filterVocabularyMemberSelectQuery(Event $event): void
    {
        $query = $event->getParam('query', []);
        $this->lastQuerySort = [
            'sort_by' => $query['sort_by'],
            'sort_order' => isset($query['sort_order']) && strtolower((string) $query['sort_order']) === 'desc' ? 'desc' : 'asc',
        ];
    }

    public function filterVocabularyMemberSelectValues(Event $event): void
    {
        if ($this->lastQuerySort['sort_by'] !== 'label') {
            $this->lastQuerySort = [];
            return;
        }

        // TODO Check for last upgrade of Omeka S.

        // TODO Replace this event by a upper level sql event. May require insertion of translated terms in a table (automatically via rdf or po files?).

        $valueOptions = $event->getParam('valueOptions', []);

        // During this event, the labels are not yet translated by Zend form.
        // They must not be translated twice.
        $translator = $this->getServiceLocator()->get('MvcTranslator');
        // TODO natcasesort() doesn't manage accented letters ("É" is after "Z") (will be fixed by sql event?).

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
        $translateLabels = function ($v) use ($translator) {
            return is_array($v) ? $translator->translate($v['label']) : $translator->translate($v);
        };
        $appendedTranslated = array_map($translateLabels, $appended);
        natcasesort($appendedTranslated);
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
        $translateOptionsLabels = function ($v) use ($translator, $reverted) {
            if (is_scalar($v) || empty($v['options'])) {
                return $v;
            }
            $optionLabelsTranslated = array_map(function ($vv) use ($translator) {
                return $translator->translate($vv['label']);
            }, $v['options']);
            natcasesort($optionLabelsTranslated);
            if ($reverted) {
                $optionLabelsTranslated = array_reverse($optionLabelsTranslated, true);
            }
            $this->lastQuerySort['sort_by'];
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
            ]);
    }

    public function handleSiteSettings(Event $event): void
    {
        $this->handleAnySettings($event, 'site_settings');
        $this->prepareSiteLocales();
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
                'type' => \Internationalisation\Form\Element\SitesPageSelect::class,
                'options' => [
                    'label' => 'Translations', // @translate
                    'info' => 'The selected pages will be translations of the current page within a site group, that must be defined. The language switcher displays only one related page by site.', // @translate
                    'site_group' => 'internationalisation_site_groups',
                    'exclude_current_site' => true,
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
        echo <<<INLINE
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
                    .before('<a href="#" class="expand" aria-label=$expand>' + $legend + ' </a> ');
            });
            </script>
            INLINE;
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
                'link_job' => sprintf('<a href="%s">',
                    htmlspecialchars($urlHelper('admin/id', ['controller' => 'job', 'id' => $job->getId()]))
                ),
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
                $isoCodes = \Iso639p3::codes($locale);
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
     * List locales according to the request.
     *
     * @return array
     */
    protected function getLocales()
    {
        static $locales;

        if (is_null($locales)) {
            $locales = [];

            // Currently limited to public front-end.
            // TODO In admin, use the user settings or add some main settings.
            $services = $this->getServiceLocator();

            /** @var \Omeka\Mvc\Status $status */
            $status = $services->get('Omeka\Status');
            if ($status->isSiteRequest()) {
                /** @var \Omeka\Settings\SiteSettings $siteSettings */
                $siteSettings = $services->get('Omeka\Settings\Site');

                // FIXME Remove the exception that occurs with background job and api during update: job seems to set status as site.
                try {
                    $locales = $siteSettings->get('internationalisation_locales', []);
                } catch (\Exception $e) {
                    // Probably a background process.
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
