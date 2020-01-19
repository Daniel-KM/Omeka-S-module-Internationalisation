<?php
namespace Internationalisation;

if (!class_exists(\Generic\AbstractModule::class)) {
    require file_exists(dirname(__DIR__) . '/Generic/AbstractModule.php')
        ? dirname(__DIR__) . '/Generic/AbstractModule.php'
        : __DIR__ . '/src/Generic/AbstractModule.php';
}

use Generic\AbstractModule;
use Omeka\Settings\SiteSettings;
use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\Mvc\MvcEvent;

class Module extends AbstractModule
{
    const NAMESPACE = __NAMESPACE__;

    /**
     * @var array
     */
    protected $cacheLocaleValues = [];

    public function onBootstrap(MvcEvent $event)
    {
        parent::onBootstrap($event);
        $this->addAclRules();
    }

    /**
     * Add ACL rules for this module.
     */
    protected function addAclRules()
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

    protected function preInstall()
    {
        $vendor = __DIR__ . '/vendor/daniel-km/simple-iso-639-3/src/Iso639p3.php';
        if (!file_exists($vendor)) {
            $services = $this->getServiceLocator();
            $t = $services->get('MvcTranslator');
            throw new \Omeka\Module\Exception\ModuleCannotInstallException(
                $t->translate('The composer vendor is not ready.') // @translate
                    . ' ' . $t->translate('See module’s installation documentation.') // @translate
            );
        }
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        $sharedEventManager->attach(
            '*',
            'view.layout',
            [$this, 'handleViewLayoutPublic']
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

        // Add the related pages to the representation of the pages.
        $sharedEventManager->attach(
            \Omeka\Api\Representation\SitePageRepresentation::class,
            'rep.resource.json',
            [$this, 'filterJsonLd']
        );

        $sharedEventManager->attach(
            \Omeka\Api\Adapter\SitePageAdapter::class,
            'api.update.post',
            [$this, 'handleApiUpdatePostPage']
        );

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
        $sharedEventManager->attach(
            \Omeka\Form\SiteSettingsForm::class,
            'form.add_input_filters',
            [$this, 'handleSiteSettingsFilters']
        );
    }

    public function handleViewLayoutPublic(Event $event)
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
     * Order values of each property according to settings, without filtering.
     *
     * @param Event $event
     */
    public function handleResourceValues(Event $event)
    {
        // Currently limited to public front-end.
        // TODO In admin, use the user settings or add some main settings.
        $services = $this->getServiceLocator();
        /** @var \Omeka\Mvc\Status $status */
        $status = $services->get('Omeka\Status');
        if (!$status->isSiteRequest()) {
            return;
        }

        /** @var \Omeka\Settings\SiteSettings $settings */
        $settings = $services->get('Omeka\Settings\Site');

        // FIXME Remove the exception that occurs with background job and api during update: job seems to set status as site.
        try {
            $locales = $settings->get('internationalisation_locales', []);
        } catch (\Exception $e) {
            // Probably background process.
            return;
        }

        if (empty($locales)) {
            return;
        }

        $resourceId = $event->getTarget()->id();
        $this->cacheLocaleValues[$resourceId] = [];

        // Order values for each property according to settings.
        $values = $event->getParam('values');
        foreach ($values as $term => &$valueInfo) {
            $valuesByLang = $locales;
            foreach ($valueInfo['values'] as $value) {
                $valuesByLang[$value->lang()][] = $value;
            }
            $valuesByLang = array_filter($valuesByLang);
            $this->cacheLocaleValues[$resourceId][$term] = $valuesByLang;
            $valueInfo['values'] = $valuesByLang
                ? array_merge(...array_values($valuesByLang))
                : [];
        }

        $event->setParam('values', $values);
    }

    /**
     * Filter values of each property according to settings.
     *
     * Note: the values are already ordered by language in previous event.
     *
     * @param Event $event
     */
    public function handleResourceDisplayValues(Event $event)
    {
        $services = $this->getServiceLocator();
        $status = $services->get('Omeka\Status');
        if (!$status->isSiteRequest()) {
            return;
        }

        /** @var \Omeka\Settings\SiteSettings $settings */
        $settings = $services->get('Omeka\Settings\Site');
        $locale = $settings->get('locale');
        if (empty($locale)) {
            return;
        }

        $displayValues = $settings->get('internationalisation_display_values', 'all');
        if (in_array($displayValues, ['all', 'all_site', 'all_iso', 'all_fallback'])) {
            return;
        }

        $locales = $settings->get('internationalisation_locales', []);
        if (empty($locales)) {
            return;
        }

        $resourceId = $event->getTarget()->id();

        // $fallbacks = $settings->get('internationalisation_fallbacks', []);
        $requiredLanguages = $settings->get('internationalisation_required_languages', []);

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

        $event->setParam('values', $values);
    }

    public function filterJsonLd(Event $event)
    {
        $page = $event->getTarget();
        $jsonLd = $event->getParam('jsonLd');
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');
        $pageId = $page->id();
        $relations = $api
            ->search(
                'site_page_relations',
                ['relation' => $pageId]
            )
            ->getContent();
        $relations = array_map(function ($relation) use ($pageId) {
            $related = $relation->relatedPage();
            return $pageId === $related->id()
                ? $relation->page()->getReference()
                : $related->getReference();
        }, $relations);
        $jsonLd['o-module-internationalisation:related_page'] = $relations;
        $event->setParam('jsonLd', $jsonLd);
    }

    public function handleApiUpdatePostPage(Event $event)
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
        foreach ($ids as $id) {
            foreach ($relatedIds as $relatedId) {
                if ($relatedId > $id) {
                    $sql .= "\n($id, $relatedId),";
                }
            }
        }
        $sql = rtrim($sql, ',');
        $connection->exec($sql);
    }

    public function handleMainSettings(Event $event)
    {
        parent::handleMainSettings($event);

        $services = $this->getServiceLocator();

        $space = strtolower(__NAMESPACE__);

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
        $fieldset = $form->get($space);
        $siteGroupsElement = $fieldset
            ->get('internationalisation_site_groups');
        $siteGroupsElement
            ->setValue($siteGroupsString)
            ->setRestoreButtonText('Remove all groups') // @translate
            ->setRestoreValue(implode("\n", $sites));
    }

    public function handleMainSettingsFilters(Event $event)
    {
        $event->getParam('inputFilter')
            ->get('internationalisation')
            ->add([
                'name' => 'internationalisation_site_groups',
                'required' => false,
                'filters' => [
                    [
                        'name' => \Zend\Filter\Callback::class,
                        'options' => [
                            'callback' => [$this, 'filterSiteGroups'],
                        ],
                    ],
                ],
            ]);
    }

    public function handleSiteSettings(Event $event)
    {
        parent::handleSiteSettings($event);

        $services = $this->getServiceLocator();

        $space = strtolower(__NAMESPACE__);

        $settings = $services->get('Omeka\Settings\Site');

        /**
         * @var \Omeka\Form\Element\RestoreTextarea $siteGroupsElement
         * @var \Internationalisation\Form\SettingsFieldset $fieldset
         */
        $fieldset = $event->getTarget()
            ->get($space);
        $list = $settings->get('internationalisation_fallbacks') ?: [];
        $fieldset
            ->get('internationalisation_fallbacks')
            ->setValue(implode("\n", $list));
        $list = $settings->get('internationalisation_required_languages') ?: [];
        $fieldset
            ->get('internationalisation_required_languages')
            ->setValue(implode("\n", $list));

        $this->prepareSiteLocales($settings);
    }

    /**
     * For performance, save ordered locales and iso codes when needed.
     *
     * It's not possible to save it simply after validation, so add it here,
     * since the form is always reloaded after submission.
     *
     * @param SiteSettings $settings
     */
    protected function prepareSiteLocales(SiteSettings $settings)
    {
        $settings->set('internationalisation_iso_codes', []);

        $locale = $settings->get('locale');
        if (!$locale) {
            $settings->set('internationalisation_locales', []);
            return;
        }

        $displayValues = $settings->get('internationalisation_display_values', 'all');
        if ($displayValues === 'all') {
            $settings->set('internationalisation_locales', []);
            return;
        }

        // Prepare the locales.
        $locales = [$locale];
        switch ($displayValues) {
            case 'all_site_iso':
            case 'site_iso':
                require_once __DIR__ . '/vendor/daniel-km/simple-iso-639-3/src/Iso639p3.php';
                $isoCodes = \Iso639p3::codes($locale);
                $settings->set('internationalisation_iso_codes', $isoCodes);
                $locales += $isoCodes;
                break;

            case 'all_fallback':
            case 'site_fallback':
                $locales += $settings->get('internationalisation_fallbacks', []);
                break;

            case 'all_site':
            case 'site':
                // Nothing to do.
                break;

            default:
                $settings->set('internationalisation_display_values', 'all');
                $settings->set('internationalisation_locales', []);
                return;
        }

        $requiredLanguages = $settings->get('internationalisation_required_languages', []);
        $locales += $requiredLanguages;
        $locales = array_fill_keys(array_unique(array_filter($locales)), []);

        // Add a fallback for values without language in all cases,
        // because in many cases default language is not set.
        // TODO Set an option to not fallback to values without language?
        $locales[''] = [];

        $settings->set('internationalisation_locales', $locales);
    }

    public function handleSiteSettingsFilters(Event $event)
    {
        $inputFilter = $event->getParam('inputFilter');
        $inputFilter->get('internationalisation')
            ->add([
                'name' => 'internationalisation_fallbacks',
                'required' => false,
                'filters' => [
                    [
                        'name' => \Zend\Filter\Callback::class,
                        'options' => [
                            'callback' => [$this, 'stringToList'],
                        ],
                    ],
                ],
            ])
            ->add([
                'name' => 'internationalisation_required_languages',
                'required' => false,
                'filters' => [
                    [
                        'name' => \Zend\Filter\Callback::class,
                        'options' => [
                            'callback' => [$this, 'stringToList'],
                        ],
                    ],
                ],
            ]);
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
}
