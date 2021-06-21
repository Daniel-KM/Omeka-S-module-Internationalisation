<?php declare(strict_types=1);

namespace Internationalisation\Site\BlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Manager as ApiManager;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Entity\SitePage;
use Omeka\Entity\SitePageBlock;
use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Omeka\Stdlib\ErrorStore;

/**
 * Copy of the same block from module BlockPlus.
 *
 * @see \BlockPlus\Site\BlockLayout\MirrorPage
 */
class MirrorPage extends AbstractBlockLayout
{
    /**
     * The default partial view script.
     */
    const PARTIAL_NAME = null;

    /**
     * @var ApiManager
     */
    protected $api;

    /**
     * @param ApiManager $api
     */
    public function __construct(ApiManager $api)
    {
        $this->api = $api;
    }

    public function getLabel()
    {
        return 'Mirror page'; // @translate
    }

    public function onHydrate(SitePageBlock $block, ErrorStore $errorStore): void
    {
        $mirrorPage = (int) $block->getData()['page'] ?: $this->defaultSettings['page'];

        if (empty($mirrorPage)) {
            $errorStore->addError('o:block[__blockIndex__][o:data][page]', 'A page should be selected to create a mirror page.'); // @translate
            return;
        }

        if ($mirrorPage === $block->getPage()->getId()) {
            $errorStore->addError('o:block[__blockIndex__][o:data][page]', 'A mirror page cannot be inside itself.'); // @translate
            return;
        }

        // A page cannot be searched by id, so try read.
        try {
            $response = $this->api->read('site_pages', ['id' => $mirrorPage], [], ['responseContent' => 'resource']);
        } catch (\Omeka\Api\Exception\NotFoundException $e) {
            $errorStore->addError('o:block[__blockIndex__][o:data][page]', 'A mirror page cannot use a page that uses it recursively as a block.'); // @translate
            return;
        }
        $mirrorPage = $response->getContent();

        if (!$this->checkMirrorPage($block->getPage(), $mirrorPage)) {
            $errorStore->addError('o:block[__blockIndex__][o:data][page]', 'A mirror page cannot use a page that uses it recursively as a block.'); // @translate
            return;
        }
    }

    public function form(
        PhpRenderer $view,
        SiteRepresentation $site,
        SitePageRepresentation $page = null,
        SitePageBlockRepresentation $block = null
    ) {
        // Factory is not used to make rendering simpler.
        $services = $site->getServiceLocator();
        $formElementManager = $services->get('FormElementManager');
        $defaultSettings = $services->get('Config')['blockplus']['block_settings']['mirrorPage'];
        $blockFieldset = \BlockPlus\Form\MirrorPageFieldset::class;

        $data = $block ? $block->data() + $defaultSettings : $defaultSettings;

        $dataForm = [];
        foreach ($data as $key => $value) {
            $dataForm['o:block[__blockIndex__][o:data][' . $key . ']'] = $value;
        }

        $fieldset = $formElementManager->get($blockFieldset);
        $fieldset->populateValues($dataForm);

        $html = '<p>'
            . $view->translate('Choose any page from any site.') // @translate
            . ' ' . $view->translate('If a page of a private site is selected, it will be hidden on the public side.') // @translate
            . ' ' . $view->translate('The current page and recursive pages are forbidden.') // @translate
            . '</p>';
        $html .= $view->formCollection($fieldset, false);
        return $html;
    }

    public function render(PhpRenderer $view, SitePageBlockRepresentation $block)
    {
        $mirrorPage = $block->dataValue('page');

        try {
            $response = $view->api()->read('site_pages', ['id' => $mirrorPage]);
        } catch (\Omeka\Api\Exception\NotFoundException $e) {
            $view->logger()->err(sprintf(
                'Mirror page block #%d of page "%s" of site "%s" should be updated: it refers to a removed page.', // @translate
                $block->id(),
                $block->page()->slug(),
                $block->page()->site()->slug()
            ));
            return '';
        } catch (\Omeka\Api\Exception\PermissionDeniedException $e) {
            return '';
        }

        /** @var \Omeka\Api\Representation\SitePageRepresentation $mirrorPage */
        $mirrorPage = $response->getContent();

        // The page cannot be rendered by the partial directly, because some
        // cases should be fixed.

        // @see \Omeka\Controller\Site\PageController::showAction()
        $contentView = new \Laminas\View\Model\ViewModel([
            'site' => $mirrorPage->site(),
            'page' => $mirrorPage,
        ]);
        $contentView->setTemplate('omeka/site/page/content');
        // This fixes the block Table Of Contents.
        $contentView->setVariable('pageViewModel', $contentView);
        try {
            return $view->render($contentView);
        } catch (\Exception $e) {
            $view->logger()->err(sprintf(
                'Cannot render this mirror page for now: %s.', // @translate
                $e
            ));
            return '';
        }
    }

    public function getFulltextText(PhpRenderer $view, SitePageBlockRepresentation $block)
    {
        // The site and page slugs should be set in the route to be able to
        // create url in background.
        $page = $block->page();
        $site = $page->site();

        /** @var \Omeka\Mvc\Status $status */
        $services = $block->getServiceLocator();
        $status = $services->get('Omeka\Status');
        $routeMatch = $status->getRouteMatch();
        // There may be no route match for a job in backend.
        // TODO Fix indexing full text for mirror page with the job run from Bulk Import.
        if (!$routeMatch) {
            return;
        }
        $routeMatch
            ->setParam('site-slug', $site->slug())
            ->setParam('page-slug', $page->slug());

        // When site settings are used, the render may fail because the target
        // is not set.
        // @see \Omeka\Mvc\MvcListeners::prepareSite()
        $services->get('Omeka\Settings\Site')->setTargetId($site->id());
        $services->get('ControllerPluginManager')->get('currentSite')->setSite($site);

        $themeManager = $services->get('Omeka\Site\ThemeManager');
        $currentTheme = $themeManager->getTheme($site->theme());
        if (!$currentTheme) {
            $currentTheme = new \Omeka\Site\Theme\Theme('not_found');
            $currentTheme->setState(\Omeka\Site\Theme\Manager::STATE_NOT_FOUND);
        }
        $themeManager->setCurrentTheme($currentTheme);

        // TODO Many blocks are not indexed. Why indexing them in mirror pages?
        try {
            return strip_tags($this->render($view, $block));
        } catch (\Exception $e) {
            return '';
        }
    }

    /**
     * Recursively check if a mirror page belongs to itself via recursive blocks.
     *
     * @param SitePage $page
     * @param SitePage $mirrorPage
     * @param array $blocks
     * @return bool
     */
    protected function checkMirrorPage(SitePage $page, SitePage $mirrorPage)
    {
        if ($page->getId() === $mirrorPage->getId()) {
            return false;
        }

        foreach ($mirrorPage->getBlocks() as $block) {
            if ($block->getLayout() === 'mirrorPage') {
                if ($page->getId() === $block->getData()['page']) {
                    return false;
                }
                try {
                    $response = $this->api->read('site_pages', ['id' => $block->getData()['page']], [], ['responseContent' => 'resource']);
                } catch (\Omeka\Api\Exception\NotFoundException $e) {
                    return false;
                }
                $mirrorPage = $response->getContent();
                if (!$this->checkMirrorPage($page, $mirrorPage)) {
                    return false;
                }
            }
        }

        return true;
    }
}
