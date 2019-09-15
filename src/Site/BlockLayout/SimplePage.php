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

class SimplePage extends AbstractBlockLayout
{
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
        return 'Simple page'; // @translate
    }

    public function onHydrate(SitePageBlock $block, ErrorStore $errorStore)
    {
        $simplePage = (int) $block->getData()['page'] ?: $this->defaultSettings['page'];

        if (empty($simplePage)) {
            $errorStore->addError('o:block[__blockIndex__][o:data][page]', 'A page should be selected to create a simple page.'); // @translate
            return;
        }

        if ($simplePage === $block->getPage()->getId()) {
            $errorStore->addError('o:block[__blockIndex__][o:data][page]', 'A simple page cannot be inside itself.'); // @translate
            return;
        }

        // A page cannot be searched by id, so try read.
        try {
            $response = $this->api->read('site_pages', ['id' => $simplePage], [], ['responseContent' => 'resource']);
        } catch (\Omeka\Api\Exception\NotFoundException $e) {
            $errorStore->addError('o:block[__blockIndex__][o:data][page]', 'A simple page cannot use a page that uses it recursively as a block.'); // @translate
            return;
        }
        $simplePage = $response->getContent();

        if (!$this->checkSimplePage($block->getPage(), $simplePage)) {
            $errorStore->addError('o:block[__blockIndex__][o:data][page]', 'A simple page cannot use a page that uses it recursively as a block.'); // @translate
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
        $defaultSettings = $services->get('Config')['internationalisation']['block_settings']['simplePage'];
        $blockFieldset = \Internationalisation\Form\SimplePageFieldset::class;

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
        $simplePage = $block->dataValue('page');

        // A page cannot be searched by id, so try read.
        try {
            $response = $view->api()->read('site_pages', ['id' => $simplePage]);
        } catch (\Omeka\Api\Exception\NotFoundException $e) {
            $view->logger()->err(sprintf(
                'Simple page block #%d of page "%s" of site "%s" should be updated: it refers to a removed page.', // @translate
                $block->id(),
                $block->page()->slug(),
                $block->page()->site()->slug()
            ));
            return '';
        } catch (\Omeka\Api\Exception\PermissionDeniedException $e) {
            return '';
        }
        $simplePage = $response->getContent();

        return $view->partial('omeka/site/page/content', [
            'page' => $simplePage,
        ]);
    }

    public function getFulltextText(PhpRenderer $view, SitePageBlockRepresentation $block)
    {
        // TODO Many blocks are not indexed. Why indexing them in simple pages?
        return strip_tags($this->render($view, $block));
    }

    /**
     * Recursively check if a simple page belongs to itself via recursive blocks.
     *
     * @param SitePage $page
     * @param SitePage $simplePage
     * @param array $blocks
     * @return bool
     */
    protected function checkSimplePage(SitePage $page, SitePage $simplePage)
    {
        if ($page->getId() === $simplePage->getId()) {
            return false;
        }

        foreach ($simplePage->getBlocks() as $block) {
            if ($block->getLayout() === 'simplePage') {
                if ($page->getId() === $block->getData()['page']) {
                    return false;
                }
                try {
                    $response = $this->api->read('site_pages', ['id' => $block->getData()['page']], [], ['responseContent' => 'resource']);
                } catch (\Omeka\Api\Exception\NotFoundException $e) {
                    return false;
                }
                $simplePage = $response->getContent();
                if (!$this->checkSimplePage($page, $simplePage)) {
                    return false;
                }
            }
        }

        return true;
    }
}
