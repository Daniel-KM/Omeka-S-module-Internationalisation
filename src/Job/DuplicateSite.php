<?php

namespace Internationalisation\Job;

use Internationalisation\Entity\SitePageRelation;
use Omeka\Entity\Site;
use Omeka\Entity\SitePage;
use Omeka\Job\AbstractJob;
use Omeka\Mvc\Exception\NotFoundException;
use Omeka\Stdlib\Message;

class DuplicateSite extends AbstractJob
{
    /**
     * @var \Zend\Log\Logger
     */
    protected $logger;

    /**
     * @var \Omeka\Api\Manager
     */
    protected $api;

    /**
     * @var \Omeka\Api\Adapter\SitePageAdapter
     */
    protected $pageAdapter;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entityManager;

    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $connection;

    /**
     * @var array
     */
    protected $mapPages = [];

    public function perform()
    {
        $services = $this->getServiceLocator();

        $referenceIdProcessor = new \Zend\Log\Processor\ReferenceId();
        $referenceIdProcessor->setReferenceId('internationalisation/duplicate/job_' . $this->job->getId());

        $this->logger = $services->get('Omeka\Logger');
        $this->logger->addProcessor($referenceIdProcessor);
        $this->api = $services->get('ControllerPluginManager')->get('api');
        $this->pageAdapter = $services->get('Omeka\ApiAdapterManager')->get('site_pages');
        $this->entityManager = $services->get('Omeka\EntityManager');
        $this->connection = $this->entityManager->getConnection();

        $duplicateData = $this->getArg('internationalisation_duplicate_data', ['metadata', 'settings', 'pages', 'item_pool', 'item_sets', 'permissions']);
        if (empty($duplicateData)) {
            return;
        }

        $sourceId = $this->getArg('source');
        $targetId = $this->getArg('target');
        $mode = $this->getArg('mode', 'block');
        // TODO Finalize removing of pages when false.
        $removePages = $this->getArg('remove_pages', false);
        $settings = $this->getArg('settings', []);

        try {
            /** @var \Omeka\Entity\Site $source */
            $source = $this->api->read('sites', ['id' => $sourceId], [], ['responseContent' => 'resource', 'initialize' => false, 'finalize' => false])->getContent();
        } catch (NotFoundException $e) {
        }
        if (empty($source)) {
            $this->logger->err(new Message(
                'The site #%1$s cannot be copied. Check your rights.', // @translate
                $sourceId
            ));
            $this->job->setStatus(\Omeka\Entity\Job::STATUS_ERROR);
            return;
        }

        try {
            /** @var \Omeka\Entity\Site $target */
            $target = $this->api->read('sites', ['id' => $targetId], [], ['responseContent' => 'resource', 'initialize' => false, 'finalize' => false])->getContent();
        } catch (NotFoundException $e) {
        }
        if (empty($target)) {
            $this->logger->err(new Message(
                'The site #%1$s is not available for copy. Check your rights.', // @translate
                $targetId
            ));
            $this->job->setStatus(\Omeka\Entity\Job::STATUS_ERROR);
            return;
        }

        if (in_array('settings', $duplicateData)) {
            $this->duplicateSettings($source, $target, $settings);
        }

        // Add the site to the group first to simplify duplication of pages.
        $settings = $services->get('Omeka\Settings');
        $siteGroups = $settings->get('internationalisation_site_groups') ?: [];
        if (isset($siteGroups[$source->getSlug()])) {
            $sortList = $siteGroups[$source->getSlug()];
            $sortList[] = $target->getSlug();
        } else {
            $sortList = [$source->getSlug(), $target->getSlug()];
        }
        ksort($sortList, SORT_NATURAL);
        $siteGroups[$source->getSlug()] = $sortList;
        $siteGroups[$target->getSlug()] = $sortList;
        ksort($siteGroups, SORT_NATURAL);
        $settings->set('internationalisation_site_groups', $siteGroups);

        // Remove all pages if wanted (welcome page is automatically added).
        if ($removePages) {
            $collection = $target->getPages();
            foreach ($collection->getKeys() as $key) {
                $collection->remove($key);
            }
            $this->entityManager->flush();
        }

        if (in_array('pages', $duplicateData)) {
            $this->duplicatePages($source, $target, $mode);
        }

        if (in_array('permissions', $duplicateData)) {
            $this->copySitePermissions($source, $target);
        }

        if (in_array('item_pool', $duplicateData)) {
            $this->copySiteItemPool($source, $target);
        }

        if (in_array('item_sets', $duplicateData)) {
            $this->copySiteItemSets($source, $target);
        }

        if (in_array('metadata', $duplicateData)) {
            $this->copySiteMetadata($source, $target);
        }

        if (in_array('pages', $duplicateData) || in_array('metadata', $duplicateData)) {
            $this->updateSiteMetadata($source, $target);
        }

        $this->indexPages($target);

        if ($this->job->getStatus() === \Omeka\Entity\Job::STATUS_ERROR) {
            $this->logger->warning(
                'Check logs: an error occurred.' // @translate
            );
        }
    }

    /**
     * Duplicate settings of a site.
     *
     * @param Site $source
     * @param Site $target
     * @param array $settings
     */
    protected function duplicateSettings(Site $source, Site $target, array $settings = null)
    {
        $sql = <<<SQL
INSERT INTO `site_setting` (`id`, `site_id`, `value`)
SELECT `t2`.`id`, {$target->getId()}, `t2`.`value` FROM (
    SELECT `t`.`id`, `t`.`value` FROM `site_setting` AS `t` WHERE `site_id` = {$source->getId()}
) AS `t2`
ON DUPLICATE KEY UPDATE `id`=`t2`.`id`, `site_id`={$target->getId()}, `value`=`t2`.`value`;
SQL;
        $this->connection->exec($sql);

        if (!$settings) {
            return;
        }

        /** @var \Omeka\Settings\SiteSettings $siteSettings */
        $siteSettings = $this->getServiceLocator()->get('Omeka\Settings\Site');
        $siteSettings->setTargetId($target->getId());
        foreach ($settings as $id => $value) {
            $siteSettings->set($id, $value);
        }

        $this->logger->notice(new Message(
            'Site settings of "%1$s" successfully copied into "%2$s".', // @translate
            $source->getSlug(), $target->getSlug()
        ));
    }

    /**
     * Duplicate pages of a site.
     *
     * @param Site $source
     * @param Site $target
     * @param string $mode
     */
    protected function duplicatePages(Site $source, Site $target, $mode)
    {
        // Get pages to check rights.
        if (!$source->getPages()->count()) {
            return;
        }

        /**
         * @var \Omeka\Entity\SitePage $sourcePage
         * @var \Omeka\Api\Representation\SitePageRepresentation $targetPage
         */
        foreach ($source->getPages() as $sourcePage) {
            if ($mode === 'mirror') {
                $targetPage = [
                    'o:title' => $sourcePage->getTitle(),
                    'o:slug' => $sourcePage->getSlug(),
                    'o:site' => ['o:id' => $target->getId()],
                    'o:block' => [[
                        'o:layout' => 'mirrorPage',
                        'o:data' => [
                            'page' => $sourcePage->getId(),
                        ],
                    ]],
                ];
            } else {
                // Use json_encode() instead of jsonSerialize() to get only arrays.
                $targetPage = json_decode(json_encode($this->pageAdapter->getRepresentation($sourcePage)), true);
                $targetPage['o:site'] = ['o:id' => $target->getId()];
            }
            $response = $this->api->create('site_pages', $targetPage, [], ['responseContent' => 'resource', 'initialize' => false, 'finalize' => false, 'flushEntityManager' => false]);
            if ($response) {
                $targetPage = $response->getContent();
                $this->mapPages[$sourcePage->getId()] = $targetPage;
                $this->addRelations($sourcePage, $targetPage);
            } else {
                $this->logger->err(new Message(
                    'Unable to copy page "%1$s" of site "%2$s". The page is skipped.', // @translate
                    $sourcePage->getSlug(), $source->getSlug()
                ));
                $this->job->setStatus(\Omeka\Entity\Job::STATUS_ERROR);
            }
        }

        $this->entityManager->flush();

        $this->logger->notice(new Message(
            '%1$d site pages of "%2$s" successfully copied into "%3$s" (mode "%4$s").', // @translate
            count($this->mapPages), $source->getSlug(), $target->getSlug(), $mode
        ));
    }

    protected function copySitePermissions(Site $source, Site $target)
    {
        $sql = <<<SQL
INSERT INTO `site_permission` (`site_id`, `user_id`, `role`)
SELECT {$target->getId()}, `t2`.`user_id`, `t2`.`role` FROM (
    SELECT `t`.`site_id`, `t`.`user_id`, `t`.`role` FROM `site_permission` AS `t` WHERE `site_id` = {$source->getId()}
) AS `t2`
ON DUPLICATE KEY UPDATE `site_id`={$target->getId()}, `user_id`=`t2`.`user_id`, `role`=`t2`.`role`;
SQL;
        $this->connection->exec($sql);
        $this->entityManager->refresh($target);
    }

    protected function copySiteItemPool(Site $source, Site $target)
    {
        $target->setItemPool($source->getItemPool());
        $this->entityManager->refresh($target);
    }

    protected function copySiteItemSets(Site $source, Site $target)
    {
        $sql = <<<SQL
INSERT INTO `site_item_set` (`site_id`, `item_set_id`, `position`)
SELECT {$target->getId()}, `t2`.`item_set_id`, `t2`.`position` FROM (
    SELECT `t`.`site_id`, `t`.`item_set_id`, `t`.`position` FROM `site_item_set` AS `t` WHERE `site_id` = {$source->getId()}
) AS `t2`
ON DUPLICATE KEY UPDATE `site_id`={$target->getId()}, `item_set_id`=`t2`.`item_set_id`, `position`=`t2`.`position`;
SQL;
        $this->connection->exec($sql);
        $this->entityManager->refresh($target);
    }

    protected function copySiteMetadata(Site $source, Site $target)
    {
        // $target->setTitle($source->getTitle());
        // $target->setSummary($source->getSummary());
        // $target->setIsPublic($source->isPublic());
        $target->setTheme($source->getTheme());
        $target->setHomepage($source->getHomepage());
        $target->setNavigation($source->getNavigation());
        // $target->setSitePermissions($source->getSitePermissions());
        // $target->setItemPool($source->getItemPool());
        // $target->setSiteItemSets($source->getSiteItemSets());
        $this->entityManager->flush();
    }

    protected function updateSiteMetadata(Site $source, Site $target)
    {
        $target->setTheme($source->getTheme());

        $homepage = $source->getHomepage();
        if ($homepage && isset($this->mapPages[$homepage->getId()])) {
            $target->setHomepage($this->mapPages[$homepage->getId()]);
        } else {
            $target->setHomepage(null);
        }

        $homepage = $source->getHomepage();
        if ($homepage && isset($this->mapPages[$homepage->getId()])) {
            $target->setHomepage($this->mapPages[$homepage->getId()]);
        } else {
            $target->setHomepage(null);
        }

        $navigation = $source->getNavigation();
        $iterate = function (&$navigation) use (&$iterate) {
            foreach ($navigation as &$data) {
                if ($data['type'] === 'page' && !empty($this->mapPages[$data['data']['id']])) {
                    $data['data']['id'] = $this->mapPages[$data['data']['id']]->getId();
                }
                if (isset($data['links'])) {
                    $iterate($data['links']);
                }
            }
        };
        $iterate($navigation);
        $target->setNavigation($navigation);

        $this->entityManager->flush();
    }

    protected function indexPages(Site $site)
    {
        /**
         * @var \Omeka\Stdlib\FulltextSearch $fulltext
         */
        $fulltext = $this->getServiceLocator()->get('Omeka\FulltextSearch');
        foreach ($site->getPages() as $page) {
            $fulltext->save($page, $this->pageAdapter);
        }
    }

    protected function addRelations(SitePage $sourcePage, SitePage $targetPage)
    {
        /** @var \Internationalisation\Entity\SitePageRelation[] $relations */
        $relations = $this->api->search('site_page_relations', ['relation' => $sourcePage->getId()], ['responseContent' => 'resource', 'initialize' => false, 'finalize' => false, 'flushEntityManager' => false])->getContent();

        // As page/related page cannot be known, a list is made first.
        $list = [$sourcePage->getId() => $sourcePage];
        foreach ($relations as $relation) {
            $list[$relation->getPage()->getId()] = $relation->getPage();
            $list[$relation->getRelatedPage()->getId()] = $relation->getRelatedPage();
        }
        foreach ($list as $relatedPage) {
            $newRelatedPage = new SitePageRelation();
            $newRelatedPage
                ->setPage($relatedPage)
                ->setRelatedPage($targetPage);
            $this->entityManager->persist($newRelatedPage);
        }
    }
}
