<?php
namespace LanguageSwitcher;

require_once dirname(__DIR__) . '/Generic/AbstractModule.php';
use Generic\AbstractModule;
use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;
use Zend\Mvc\MvcEvent;

class Module extends AbstractModule
{
    const NAMESPACE = __NAMESPACE__;

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
                [\LanguageSwitcher\Api\Adapter\SitePageRelationAdapter::class],
                ['search', 'read']
            );
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        $sharedEventManager->attach(
            '*',
            'view.layout',
            [$this, 'handleViewLayoutPublic']
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
    }

    public function handleViewLayoutPublic(Event $event)
    {
        $view = $event->getTarget();
        if ($view->params()->fromRoute('__ADMIN__')) {
            return;
        }

        $view->headLink()
            ->appendStylesheet($view->assetUrl('css/language-switcher.css', 'LanguageSwitcher'))
            ->appendStylesheet($view->assetUrl('vendor/flag-icon-css/css/flag-icon.min.css', 'LanguageSwitcher'));
    }

    public function filterJsonLd(Event $event)
    {
        $page = $event->getTarget();
        $jsonLd = $event->getParam('jsonLd');
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');
        // TODO Page visibility is automatically checked?
        $relations = $api
            ->search(
                'site_page_relations',
                ['page_id' => $page->id()]
                // ['returnScalar' => 'relatedPage']
            )
            ->getContent();
        $relations = array_map(function($relation) {
            return $relation->relatedPage()->getReference();
        }, $relations);
        $jsonLd['o-module-language-switcher:related_page'] = $relations;
        $event->setParam('jsonLd', $jsonLd);
    }

    public function handleApiUpdatePostPage (Event $event)
    {
        /** @var \Omeka\Api\Manager $api */
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');
        /** @var \Omeka\Api\Request $request */
        $request = $event->getParam('request');
        $response = $event->getParam('response');
        $pageId = $response->getContent()->getId();

        $selected = $request->getValue('o-module-language-switcher:related_page', []);
        $selected = array_map('intval', $selected);

        // Get the existing relations to check if some of them were removed.
        $existing = $api
            ->search(
                'site_page_relations',
                ['page_id' => $pageId]
                // ['returnScalar' => 'relatedPage']
            )
            ->getContent();
        $existing = array_map(function($pageRelation) {
            return $pageRelation->relatedPage()->id();
        }, $existing);

        $added = array_diff($selected, $existing);
        $removed = array_diff($existing, $selected);
        // $kept = array_intersect($selected, $existing);

        foreach ($added as $relation) {
            $api->create('site_page_relations', ['o:page' => ['o:id' => $pageId], 'o-module-language-switcher:related_page' => ['o:id' => $relation]]);
        }
        foreach ($removed as $relation) {
            $api->delete('site_page_relations', ['page' => $pageId, 'relatedPage' => $relation]);
        }
    }
}
