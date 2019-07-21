<?php
namespace LanguageSwitcher\View\Helper;

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
     * @var array
     */
    protected $localeLabels;

    /**
     * @param array $localeSites
     * @param array $localeLabels
     */
    public function __construct(array $localeSites, array $localeLabels)
    {
        $this->localeSites = $localeSites;
        $this->localeLabels = $localeLabels;
    }

    /**
     * Render the language switcher.
     *
     * @param string|null $partialName Name of view script, or a view model
     * @return string
     */
    public function __invoke($partialName = null)
    {
        $locales = $this->localeSites;
        if (count($locales) <= 1) {
            return '';
        }

        $view = $this->getView();

        $site = $view->vars()->site;
        if (empty($site)) {
            return '';
        }

        $urlHelper = $view->plugin('Url');

        // No check is done: we suppose that the translated sites have the same
        // item pool, etc.
        $params = $view->params();
        $controller = $params->fromRoute('__CONTROLLER__');

        $data = [];
        if ($controller === 'Page') {
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
        } else {
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
