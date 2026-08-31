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

    /**
     * Cache of "site slug => scheme://domain" when module DomainManager is
     * used. Null until resolved, then an array (possibly empty).
     *
     * @var array|null
     */
    protected $siteDomains;

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
    public function __invoke(?string $type = null)
    {
        if ($type === null) {
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
        // The core site adapter does not support an array of slugs,
        // so check via a full sites id/slug load.
        if (!is_array(reset($siteGroup))) {
            $siteGroupKeys = array_fill_keys(array_filter($siteGroup, 'is_scalar'), true);
        } else {
            $siteGroupKeys = array_fill_keys(array_keys($siteGroup), true);
        }
        $locales = array_intersect_key($this->localeSites, $siteGroupKeys);

        $urlHelper = $view->plugin('url');

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
            // Page may be empty if no homepage is configured.
            if (!$page) {
                return [];
            }
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
            // Pre-load pages with same slug across all sites to avoid N+1 queries.
            $pageExistsCache = [];
            if ($pageSlug) {
                $siteSlugsToCheck = array_diff(array_keys($locales), array_keys($relatedPages));
                if ($siteSlugsToCheck) {
                    // The core site adapter does not support an array of slugs,
                    // so check via a full sites id/slug load.
                    $neededSlugs = array_fill_keys($siteSlugsToCheck, true);
                    $siteIdsBySlug = [];
                    foreach ($api->search('sites', [], ['returnScalar' => 'slug'])->getContent() as $siteId => $siteSlug) {
                        if (isset($neededSlugs[$siteSlug])) {
                            $siteIdsBySlug[$siteSlug] = $siteId;
                        }
                    }
                    // Get all pages with the same slug across these sites in one query.
                    if ($siteIdsBySlug) {
                        $pages = $api->search('site_pages', [
                            'site_id' => array_values($siteIdsBySlug),
                            'slug' => $pageSlug,
                        ])->getContent();
                        $pageSiteIds = [];
                        foreach ($pages as $p) {
                            $pageSiteIds[$p->site()->id()] = true;
                        }
                        // Build cache: site slug => page slug if exists, else null.
                        foreach ($siteIdsBySlug as $slug => $siteId) {
                            $pageExistsCache[$slug] = isset($pageSiteIds[$siteId]) ? $pageSlug : null;
                        }
                    }
                }
            }

            foreach ($locales as $siteSlug => $localeId) {
                if (isset($relatedPages[$siteSlug])) {
                    // Use the translated page.
                    $url = $urlHelper(null, ['site-slug' => $siteSlug, 'page-slug' => $relatedPages[$siteSlug]], true);
                } elseif ($pageSlug && isset($pageExistsCache[$siteSlug]) && $pageExistsCache[$siteSlug]) {
                    // Page with same slug exists in target site.
                    $url = $urlHelper(null, ['site-slug' => $siteSlug, 'page-slug' => $pageExistsCache[$siteSlug]], true);
                } elseif ($pageSlug) {
                    // No translation and no same-slug page: stay on current page.
                    $url = $urlHelper(null, ['site-slug' => $currentSiteSlug, 'page-slug' => $pageSlug], true);
                } else {
                    // Homepage: link to the target site homepage.
                    $url = $urlHelper(null, ['site-slug' => $siteSlug], true);
                }
                $data[] = [
                    'site' => $siteSlug,
                    'locale' => $localeId,
                    'locale_label' => $this->localeLabels[$localeId] ?? $localeId,
                    'url' => $url,
                ];
            }

            return $this->applyDomains($data);
        }

        // Manage module AdvancedSearch (that has only one action, but multiple paths).
        if (($controller === 'AdvancedSearch\Controller\SearchController' || $controller === 'AdvancedSearch\Controller\IndexController')
            && $pageSlug = $params->fromRoute('page-slug')
        ) {
            // It's not possible to use siteSettings for another site from the view. See git history.

            // TODO Save all the relations between search pages in a setting to avoid to prepare it each time.
            $api = $view->api();
            /** @var \Doctrine\DBAL\Connection $connection */
            $services = $site->getServiceLocator();
            $connection = $services->get('Omeka\Connection');
            $searchPageIdsBySite = $connection->fetchAllKeyValue('SELECT `site_id`, `value` FROM `site_setting` WHERE `id` = "advancedsearch_configs";');
            $searchPageIdsBySite = array_map(fn ($v) => json_decode($v, true), $searchPageIdsBySite);
            $mainSearchPageIdBySite = $connection->fetchAllKeyValue('SELECT `site_id`, `value` FROM `site_setting` WHERE `id` = "advancedsearch_main_config";');
            $mainSearchPageIdBySite = array_map(fn ($v) => json_decode($v, true), $mainSearchPageIdBySite);

            $query = $params->fromQuery();

            $settings = $services->get('Omeka\Settings');
            $searchPageId = $params->fromRoute('id');
            foreach ($locales as $siteSlug => $localeId) {
                $url = null;
                // TODO returnScalar is available in view helper api via a query argument.
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
                        // Module AdvancedSearch uses the slug, and module Search uses id;
                        $relatedSearchPageId = $mainSearchPageIdBySite[$relatedSiteId];
                        $searchPageSlug = $settings->get('advancedsearch_all_configs', [])[$relatedSearchPageId] ?? null;
                        if ($searchPageSlug) {
                            $url = $urlHelper('search-page-' . $searchPageSlug, ['site-slug' => $siteSlug], ['query' => $query], true);
                        }
                    }
                }

                // Fallback to the item browse page (so the result pages instead of the search page).
                if (empty($url)) {
                    $url = $urlHelper('site/resource', ['site-slug' => $siteSlug, 'controller' => 'item', 'action' => 'browse'], true);
                }

                $data[] = [
                    'site' => $siteSlug,
                    'locale' => $localeId,
                    'locale_label' => $this->localeLabels[$localeId] ?? $localeId,
                    'url' => $url,
                ];
            }

            return $this->applyDomains($data);
        }

        // Manage standard resources pages and other modules pages.
        foreach ($locales as $siteSlug => $localeId) {
            $data[] = [
                'site' => $siteSlug,
                'locale' => $localeId,
                'locale_label' => $this->localeLabels[$localeId] ?? $localeId,
                'url' => $urlHelper(null, ['site-slug' => $siteSlug], ['query' => $params->fromQuery()], true),
            ];
        }

        return $this->applyDomains($data);
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

    /**
     * Rewrite each url to the target site domain when module DomainManager maps
     * the site to a domain. Without it, links built with the current
     * (domain-based) route point back to the current domain.
     *
     * @see https://gitlab.com/Daniel-KM/Omeka-S-module-Internationalisation/-/issues/6
     */
    protected function applyDomains(array $data): array
    {
        $domains = $this->siteDomains();
        if (!$domains) {
            return $data;
        }
        // Slugs that may legitimately appear as a "/s/{slug}" prefix: any site
        // of the group and the current site. Restricting the strip to these
        // avoids cutting a clean url path that happens to start with "/s/"
        // (module CleanUrl).
        $knownSlugs = array_keys($domains);
        $current = $this->currentSite();
        if ($current) {
            $knownSlugs[] = $current->slug();
        }

        foreach ($data as &$entry) {
            $siteSlug = $entry['site'] ?? null;
            if ($siteSlug !== null
                && isset($domains[$siteSlug])
                && !empty($entry['url'])
            ) {
                $entry['url'] = $this->rewriteUrlForDomain($entry['url'], $domains[$siteSlug], $knownSlugs);
            }
        }
        unset($entry);
        return $data;
    }

    /**
     * Replace the host and a known "/s/{slug}" prefix of an url by a domain.
     *
     * Only a "/s/{slug}" whose slug is a known site slug is removed, so a clean
     * url path (module CleanUrl) starting with "/s/…" is preserved.
     *
     * @param string $domain "scheme://host" without trailing slash.
     * @param string[] $knownSlugs Site slugs that may prefix the path.
     */
    protected function rewriteUrlForDomain(string $url, string $domain, array $knownSlugs): string
    {
        $path = preg_replace('#^https?://[^/]+#', '', $url);
        $path = (string) $path;
        if ($knownSlugs && preg_match('#^/s/([^/]+)(/.*|$)#', $path, $m) && in_array($m[1], $knownSlugs, true)) {
            $path = $m[2];
        }
        $path = '/' . ltrim($path, '/');
        return $domain . $path;
    }

    /**
     * Build the map "site slug => scheme://domain" from module DomainManager.
     *
     * @return array Empty when the module is absent or no domain is mapped.
     */
    protected function siteDomains(): array
    {
        if ($this->siteDomains !== null) {
            return $this->siteDomains;
        }
        $this->siteDomains = [];

        if (!class_exists('DomainManager\Module', false)) {
            return $this->siteDomains;
        }

        $site = $this->currentSite();
        if (!$site) {
            return $this->siteDomains;
        }

        $services = $site->getServiceLocator();
        $connection = $services->get('Omeka\Connection');
        try {
            $rows = $connection->fetchAllKeyValue(
                'SELECT s.slug, m.domain FROM domain_site_mapping m INNER JOIN site s ON s.id = m.site_id'
            );
        } catch (\Throwable $e) {
            return $this->siteDomains;
        }
        if (!$rows) {
            return $this->siteDomains;
        }

        $serverUrl = $this->getView()->plugin('serverUrl');
        $scheme = parse_url((string) $serverUrl(), PHP_URL_SCHEME) ?: 'https';
        foreach ($rows as $slug => $domain) {
            $domain = trim((string) $domain);
            if ($domain === '') {
                continue;
            }
            $this->siteDomains[$slug] = $scheme . '://' . preg_replace('#^https?://#', '', $domain);
        }

        return $this->siteDomains;
    }
}
