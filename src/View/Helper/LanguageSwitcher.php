<?php
namespace Internationalisation\View\Helper;

use Omeka\Settings\SiteSettings;
use Zend\View\Helper\AbstractHelper;

/**
 * View helper for rendering the language switcher.
 */
class LanguageSwitcher extends AbstractHelper
{
    /**
     * The default partial view script.
     */
    const PARTIAL_NAME = 'common/language-switcher';

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
     * Associative array of all site that belongs to a group.
     *
     * @var array
     */
    protected $siteGroups;

    /**
     * @var SiteSettings
     */
    protected $siteSettings;

    /**
     * @param array $localeSites
     * @param array $localeLabels
     * @param array $siteGroups
     * @param SiteSettings $siteSettings
     */
    public function __construct(array $localeSites, array $localeLabels, array $siteGroups, SiteSettings $siteSettings)
    {
        $this->localeSites = $localeSites;
        $this->localeLabels = $localeLabels;
        $this->siteGroups = $siteGroups;
        $this->siteSettings = $siteSettings;
    }

    /**
     * Render the language switcher.
     *
     * @param string|null $partialName Name of view script, or a view model
     * @return string
     */
    public function __invoke($partialName = null)
    {
        $view = $this->getView();

        /** @var \Omeka\Api\Representation\SiteRepresentation $site */
        $site = $view->vars()->site;
        if (empty($site)) {
            return '';
        }

        // Only translate sites that are in a group.
        $currentSiteSlug = $site->slug();
        if (!isset($this->siteGroups[$currentSiteSlug])) {
            return '';
        }
        $siteGroup = $this->siteGroups[$currentSiteSlug];

        // Only translate sites that have at least two locales.
        // This is automatically managed since siteGroups list only them.
        $locales = array_intersect_key($this->localeSites, array_flip($siteGroup));
        if (count($locales) <= 1) {
            return '';
        }

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
            $page = $api
                ->read(
                    'site_pages',
                    ['site' => $site->id(), 'slug' => $pageSlug]
                )
                ->getContent();
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
        }

        // Manage module Search (that has only one action, but multiple paths).
        elseif ($controller === 'Search\Controller\IndexController'
            // Require module Search >= 3.5.12.
            && $pageSlug = $params->fromRoute('page-slug')
        ) {
            // TODO Save all the relations between search pages in a setting to avoid to prepare it each time.
            $api = $view->api();
            $siteSettings = $this->siteSettings;

            $searchPageId = $params->fromRoute('id');
            foreach ($locales as $siteSlug => $localeId) {
                // Option "returnScalar" is not available with view helper api.
                $relatedSite = $api->searchOne('sites', ['slug' => $siteSlug])->getContent();
                if (!$relatedSite) {
                    continue;
                }

                $siteSettings->setTargetId($relatedSite->id());
                $searchPageIds = $siteSettings->get('search_pages', []);
                // If the related site has this search engine, use it.
                if (in_array($searchPageId, $searchPageIds)) {
                    $url = $urlHelper(null, ['site-slug' => $siteSlug], true);
                }
                // Else use the main search engine of this related site.
                elseif ($searchPageIds) {
                    $searchPageId = $siteSettings->get('search_main_page', reset($searchPageIds));
                    $url = $urlHelper('search-page-' . $searchPageId, ['site-slug' => $siteSlug], true);
                }
                // Else fallback to the item search page.
                else {
                    $url = $urlHelper('site/resource', ['site-slug' => $siteSlug, 'controller' => 'item', 'action' => 'search'], true);
                }
                $data[] = [
                    'site' => $siteSlug,
                    'locale' => $localeId,
                    'locale_label' => $this->localeLabels[$localeId],
                    'url' => $url,
                ];
            }

            // Reset to the current site to avoid issues.
            $siteSettings->setTargetId($site->id());
        }

        // Manage standard resources pages and other modules pages.
        else {
            foreach ($locales as $siteSlug => $localeId) {
                $data[] = [
                    'site' => $siteSlug,
                    'locale' => $localeId,
                    'locale_label' => $this->localeLabels[$localeId],
                    'url' => $urlHelper(null, ['site-slug' => $siteSlug], true),
                ];
            }
        }

        $partialName = $partialName ?: self::PARTIAL_NAME;

        return $view->partial(
            $partialName,
            [
                'site' => $site,
                'locales' => $data,
                'locale_labels' => $this->localeLabels,
            ]
        );
    }
}
