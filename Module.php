<?php
namespace LanguageSwitcher;

if (!class_exists(\Generic\AbstractModule::class)) {
    require_once file_exists(dirname(__DIR__) . '/Generic/AbstractModule.php')
       ? dirname(__DIR__) . '/Generic/AbstractModule.php'
        : __DIR__ . '/src/Generic/AbstractModule.php';
}

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
        $pageId = $page->id();
        $relations = $api
            ->search(
                'site_page_relations',
                ['relation' => $pageId]
            )
            ->getContent();
        $relations = array_map(function($relation) use ($pageId) {
            $related = $relation->relatedPage();
            return $pageId === $related->id()
                ? $relation->page()->getReference()
                : $related->getReference();
        }, $relations);
        $jsonLd['o-module-language-switcher:related_page'] = $relations;
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

        $selected = $request->getValue('o-module-language-switcher:related_page', []);
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
        $existingIds = array_map(function($relation) use ($pageId) {
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
INSERT INTO site_page_relation
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
}
