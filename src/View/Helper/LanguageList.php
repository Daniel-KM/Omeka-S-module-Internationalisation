<?php declare(strict_types=1);

namespace Internationalisation\View\Helper;

use Laminas\View\Helper\AbstractHelper;

/**
 * View helper for rendering the language switcher.
 */
class LanguageList extends AbstractHelper
{
    /**
     * Associative array of the site slug and the site locale id.
     *
     * @var array
     */
    protected $localeSites;

    /**
     * Associative array of the locale id and the locale locale.
     *
     * @var array
     */
    protected $localeLabels;

    /**
     * Associative array of all sites that belongs to a group.
     *
     * @var array
     */
    protected $siteGroups;

    public function __construct(array $localeSites, array $localeLabels, array $siteGroups)
    {
        $this->localeSites = $localeSites;
        $this->localeLabels = $localeLabels;
        $this->siteGroups = $siteGroups;
    }

    /**
     * Return the languages lists.
     *
     * @return self|array|null
     */
    public function __invoke(string $type = null)
    {
        if (is_null($type)) {
            return $this;
        }
        if ($type === 'current_page') {
            return $this->currentPage();
        }
        if ($type === 'locale_sites') {
            return $this->localeSites;
        }
        if ($type === 'locale_labels') {
            return $this->localeLabels;
        }
        if ($type === 'site_groups') {
            return $this->siteGroups;
        }
        return null;
    }

    public function currentPage(): array
    {
        $view = $this->getView();

        $site = $this->currentSite();
        if (empty($site)) {
            return [];
        }

        // If a site is not in a group, it is a group with itself only.
        $currentSiteSlug = $site->slug();
        $siteGroup = $this->siteGroups[$currentSiteSlug]
            ?? [$currentSiteSlug => [$currentSiteSlug]];

        // Only translate sites that have at least two locales.
        // This is automatically managed since siteGroups list only them.
        // TODO Update the setting for site groups when a site is renamed.
        // $locales = array_intersect_key($this->localeSites, array_flip($siteGroup));
        // First each lang keys to allow intersect keys.
        if (!is_array(reset($siteGroup))) {
            $siteGroupKeys = array_flip($siteGroup);
        } else {
            $siteGroupKeys = [];
            foreach ($siteGroup as $key => $siteGroupElement) {
                $siteGroupKeys[$key] = true;
            }
        }
        $locales = array_intersect_key($this->localeSites, $siteGroupKeys);

        $urlHelper = $view->plugin('Url');

        // No check is done: we suppose that the translated sites have the same
        // item pool, etc.
        $params = $view->params();
        $controller = $params->fromRoute('__CONTROLLER__') ?: $params->fromRoute('controller');

        $data = [];

        // Manage standard pages.
        if ($controller === 'Page' || $controller === 'Omeka\Controller\Site\Page') {
            $api = $view->api();
            $pageSlug = $params->fromRoute('page-slug');
            if ($pageSlug) {
                $page = $api
                    ->read(
                        'site_pages',
                        ['site' => $site->id(), 'slug' => $pageSlug]
                    )
                    ->getContent();
            } else {
                // Manage home page.
                $page = $site->homepage();
            }
            // Page cannot be empty because controller is for page.
            $relations = $api
                ->search(
                    'site_page_relations',
                    ['relation' => $page->id()]
                )
                ->getContent();

            $pageId = $page->id();
            $relatedPages = [];
            foreach ($relations as $relation) {
                $related = $relation->relatedPage();
                if ($pageId === $related->id()) {
                    $related = $relation->page();
                }
                $siteSlug = $related->site()->slug();
                $relatedPages[$siteSlug] = $related->slug();
            }

            // Display a link to all site of the group, even if the locale is
            // not translated (it should).
            foreach ($locales as $siteSlug => $localeId) {
                $url = isset($relatedPages[$siteSlug])
                    ? $urlHelper(null, ['site-slug' => $siteSlug, 'page-slug' => $relatedPages[$siteSlug]], true)
                    // When a site has no matching page, it returns an error.
                    // TODO Returns the original page when it is not translated.
                    : $urlHelper(null, ['site-slug' => $siteSlug], true);
                $data[] = [
                    'site' => $siteSlug,
                    'locale' => $localeId,
                    'locale_label' => $this->localeLabels[$localeId],
                    'url' => $url,
                ];
            }

            return $data;
        }

        // Manage module AdvancedSearch (that has only one action, but multiple paths).
        if (($controller === 'AdvancedSearch\Controller\SearchController' || $controller === 'AdvancedSearch\Controller\IndexController')
            && $pageSlug = $params->fromRoute('page-slug')
        ) {
            // It's not possible to use siteSettings for another site from the view. See git history.

            // TODO Save all the relations between search pages in a setting to avoid to prepare it each time.
            $api = $view->api();
            /** @var \Doctrine\DBAL\Connection $connection */
            $connection = $site->getServiceLocator()->get('Omeka\Connection');
            $searchPageIdsBySite = $connection->fetchAllKeyValue('SELECT `site_id`, `value` FROM `site_setting` WHERE `id` = "advancedsearch_configs";');
            $searchPageIdsBySite = array_map(function ($v) {
                return json_decode($v, true);
            }, $searchPageIdsBySite);
            $mainSearchPageIdBySite = $connection->fetchAllKeyValue('SELECT `site_id`, `value` FROM `site_setting` WHERE `id` = "advancedsearch_main_config";');
            $mainSearchPageIdBySite = array_map(function ($v) {
                return json_decode($v, true);
            }, $mainSearchPageIdBySite);

            $query = $params->fromQuery();

            $searchPageId = $params->fromRoute('id');
            foreach ($locales as $siteSlug => $localeId) {
                $url = null;
                // Option "returnScalar" is not available with view helper api.
                $relatedSite = $api->searchOne('sites', ['slug' => $siteSlug])->getContent();
                if (!$relatedSite) {
                    continue;
                }

                $relatedSiteId = $relatedSite->id();
                $searchPageIds = empty($searchPageIdsBySite[$relatedSiteId]) ? [] : $searchPageIdsBySite[$relatedSiteId];

                if ($searchPageIds) {
                    // If the related site has this search engine, use it.
                    if (in_array($searchPageId, $searchPageIds)) {
                        $url = $urlHelper(null, ['site-slug' => $siteSlug], ['query' => $query], true);
                    }
                    // Else use the main search engine of this related site.
                    elseif (isset($mainSearchPageIdBySite[$relatedSiteId])) {
                        $searchPageId = $mainSearchPageIdBySite[$relatedSiteId];
                        $url = $urlHelper('search-page-' . $searchPageId, ['site-slug' => $siteSlug], ['query' => $query], true);
                    }
                }

                // Fallback to the item browse page (so the result pages instead of the search page).
                if (empty($url)) {
                    $url = $urlHelper('site/resource', ['site-slug' => $siteSlug, 'controller' => 'item', 'action' => 'browse'], true);
                }

                $data[] = [
                    'site' => $siteSlug,
                    'locale' => $localeId,
                    'locale_label' => $this->localeLabels[$localeId],
                    'url' => $url,
                ];
            }

            return $data;
        }

        // Manage standard resources pages and other modules pages.
        foreach ($locales as $siteSlug => $localeId) {
            $data[] = [
                'site' => $siteSlug,
                'locale' => $localeId,
                'locale_label' => $this->localeLabels[$localeId],
                'url' => $urlHelper(null, ['site-slug' => $siteSlug], ['query' => $params->fromQuery()], true),
            ];
        }

        return $data;
    }

    public function localeSites(): array
    {
        return $this->localeSites;
    }

    public function localeLabels(): array
    {
        return $this->localeLabels;
    }

    public function siteGroups(): array
    {
        return $this->siteGroups;
    }

    public function languageLists(): array
    {
        return [
            'locale_sites' => $this->localeSites,
            'locale_labels' => $this->localeLabels,
            'site_groups' => $this->siteGroups,
        ];
    }

    /**
     * Get the current site from the view or the root view (main layout).
     */
    protected function currentSite(): ?\Omeka\Api\Representation\SiteRepresentation
    {
        return $this->view->site ?? $this->view->site = $this->view
            ->getHelperPluginManager()
            ->get('Laminas\View\Helper\ViewModel')
            ->getRoot()
            ->getVariable('site');
    }
}
