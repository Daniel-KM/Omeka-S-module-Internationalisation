<?php

namespace Internationalisation\Site\BlockLayout;

use Omeka\Api\Manager as ApiManager;
use Omeka\Api\Representation\SitePageBlockRepresentation;
use Omeka\Api\Representation\SitePageRepresentation;
use Omeka\Api\Representation\SiteRepresentation;
use Omeka\Entity\SitePage;
use Omeka\Entity\SitePageBlock;
use Omeka\Site\BlockLayout\AbstractBlockLayout;
use Omeka\Stdlib\ErrorStore;
use Zend\View\Renderer\PhpRenderer;

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

    public function onHydrate(SitePageBlock $block, ErrorStore $errorStore)
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

        // A page cannot be searched by id, so try read.
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
        $contentView = new \Zend\View\Model\ViewModel;
        $contentView->setVariable('site', $mirrorPage->site());
        $contentView->setVariable('page', $mirrorPage);
        $contentView->setTemplate('omeka/site/page/content');
        // This fixes the block Table Of Contents.
        $contentView->setVariable('pageViewModel', $contentView);
        return $view->render($contentView);
    }

    public function getFulltextText(PhpRenderer $view, SitePageBlockRepresentation $block)
    {
        // TODO Many blocks are not indexed. Why indexing them in mirror pages?
        return strip_tags($this->render($view, $block));
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
