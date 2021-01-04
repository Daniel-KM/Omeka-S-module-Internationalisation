<?php declare(strict_types=1);

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
     * @var \Laminas\Log\Logger
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

    public function perform(): void
    {
        $services = $this->getServiceLocator();

        $referenceIdProcessor = new \Laminas\Log\Processor\ReferenceId();
        $referenceIdProcessor->setReferenceId('internationalisation/duplicate/job_' . $this->job->getId());

        $this->logger = $services->get('Omeka\Logger');
        $this->logger->addProcessor($referenceIdProcessor);
        $this->api = $services->get('ControllerPluginManager')->get('api');
        $this->pageAdapter = $services->get('Omeka\ApiAdapterManager')->get('site_pages');
        $this->entityManager = $services->get('Omeka\EntityManager');
        $this->connection = $this->entityManager->getConnection();

        $removeData = $this->getArg('remove', []);
        $copyData = $this->getArg('copy', []);
        if (empty($removeData) && empty($copyData)) {
            return;
        }

        $targetId = $this->getArg('target');
        $sourceId = $this->getArg('source');

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

        if ($sourceId) {
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
        } else {
            $copyData = [];
        }

        if ($source && count($copyData)) {
            $this->updateSiteGroups($source, $target);
            // Add the site to the group first to simplify duplication of pages.
            $settings = $services->get('Omeka\Settings');
            $siteGroups = $settings->get('internationalisation_site_groups') ?: [];
            if (isset($siteGroups[$source->getSlug()])) {
                $sortList = $siteGroups[$source->getSlug()];
                $sortList[] = $target->getSlug();
            } else {
                $sortList = [$source->getSlug(), $target->getSlug()];
            }
            $sortList = array_unique($sortList);
            ksort($sortList, SORT_NATURAL);
            $siteGroups[$source->getSlug()] = $sortList;
            $siteGroups[$target->getSlug()] = $sortList;
            ksort($siteGroups, SORT_NATURAL);
            $settings->set('internationalisation_site_groups', $siteGroups);
        }

        // First step: remove data.

        if (in_array('settings', $removeData)) {
            $settings = $this->getArg('settings', []);
            $this->removeSettings($target, $settings);
        }
        if (in_array('permissions', $removeData)) {
            $this->removeSitePermissions($target);
        }
        if (in_array('item_pool', $removeData)) {
            $this->removeSiteItemPool($target);
        }
        if (in_array('item_sets', $removeData)) {
            $this->removeSiteItemSets($target);
        }
        if (in_array('theme', $removeData)) {
            $this->removeSiteTheme($target);
        }
        if (in_array('pages', $removeData)) {
            $this->removeSitePages($target);
        }
        if (in_array('collecting', $removeData) && $this->isModuleActive('Collecting')) {
            $this->removeCollecting($target);
        }
        if (in_array('navigation', $removeData)) {
            $this->removeNavigation($target);
        }

        // Second step: copy data.

        if (in_array('settings', $copyData)) {
            $settings = $this->getArg('settings', []);
            $this->copySettings($source, $target, $settings);
        }
        if (in_array('permissions', $copyData)) {
            $this->copySitePermissions($source, $target);
        }
        if (in_array('item_pool', $copyData)) {
            $this->copySiteItemPool($source, $target);
        }
        if (in_array('item_sets', $copyData)) {
            $this->copySiteItemSets($source, $target);
        }
        if (in_array('theme', $copyData)) {
            $this->copySiteTheme($source, $target);
        }
        if (in_array('pages', $copyData)) {
            $pagesMode = $this->getArg('pages_mode', 'block');
            $this->copySitePages($source, $target, $pagesMode);
        }
        // Navigation can be updated only if pages are copied.
        if (in_array('pages', $copyData) && in_array('navigation', $copyData)) {
            $this->copySiteNavigation($source, $target);
        }
        if (in_array('collecting', $copyData) && $this->isModuleActive('Collecting')) {
            $this->copyCollecting($source, $target);
        }

        // Assign resources and reindex pages.
        $services->get(\Omeka\Job\Dispatcher::class)->dispatch(
            \Omeka\Job\UpdateSiteItems::class,
            [
                'sites' => [$targetId => $target->getItemPool()],
                'action' => 'add',
            ],
            $services->get(\Omeka\Job\DispatchStrategy\Synchronous::class)
        );

        $this->indexPages($target);

        if ($this->job->getStatus() === \Omeka\Entity\Job::STATUS_ERROR) {
            $this->logger->warn(
                'Check logs: an error occurred.' // @translate
            );
        }
    }

    protected function updateSiteGroups(Site $source, Site $target): void
    {
        // Add the site to the group first to simplify duplication of pages.
        $settings = $this->getServiceLocator()->get('Omeka\Settings');
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
    }

    protected function removeSettings(Site $site, array $settings = null): void
    {
        $sql = <<<SQL
DELETE FROM `site_setting`
WHERE `site_id` = {$site->getId()};
SQL;
        $this->connection->exec($sql);

        if (!$settings) {
            return;
        }

        /** @var \Omeka\Settings\SiteSettings $siteSettings */
        $siteSettings = $this->getServiceLocator()->get('Omeka\Settings\Site');
        $siteSettings->setTargetId($site->getId());
        foreach ($settings as $id => $value) {
            $siteSettings->set($id, $value);
        }

        $this->logger->notice(new Message(
            'Site settings of "%1$s" successfully removed.', // @translate
            $site->getSlug()
        ));
    }

    protected function removeSiteItemPool(Site $site): void
    {
        $site->setItemPool([]);
        $this->entityManager->refresh($site);
    }

    protected function removeSiteTheme(Site $site): void
    {
        $site->setTheme('default');
        $this->entityManager->refresh($site);
    }

    protected function removeNavigation(Site $site): void
    {
        $site->setHomepage(null);
        $site->setNavigation([]);
        $this->entityManager->refresh($site);
    }

    protected function removeSitePermissions(Site $site): void
    {
        $sql = <<<SQL
DELETE FROM `site_permission`
WHERE `site_id` = {$site->getId()};
SQL;
        $result = $this->connection->exec($sql);
        $this->entityManager->refresh($site);

        $this->logger->notice(new Message(
            '%1$d site permissions removed from "%2$s".', // @translate
            $result, $site->getSlug()
        ));
    }

    protected function removeSiteItemSets(Site $site): void
    {
        $sql = <<<SQL
DELETE FROM `site_item_set`
WHERE `site_id` = {$site->getId()};
SQL;
        $this->connection->exec($sql);
        $this->entityManager->refresh($site);
    }

    protected function removeSitePages(Site $site): void
    {
        // FIXME There is no "on delete cascade" on db level currently!
        $sql = <<<SQL
DELETE `site_block_attachment` FROM `site_block_attachment`
INNER JOIN `site_page_block` ON `site_page_block`.`id` = `site_block_attachment`.`block_id`
INNER JOIN `site_page` ON `site_page`.`id` = `site_page_block`.`page_id`
WHERE `site_page`.`site_id` = {$site->getId()};
SQL;
        $this->connection->exec($sql);
        $sql = <<<SQL
DELETE `site_page_block` FROM `site_page_block`
INNER JOIN `site_page` ON `site_page`.`id` = `site_page_block`.`page_id`
WHERE `site_page`.`site_id` = {$site->getId()};
SQL;
        $this->connection->exec($sql);
        $sql = <<<SQL
DELETE FROM `site_page`
WHERE `site_id` = {$site->getId()};
SQL;
        $result = $this->connection->exec($sql);
        $this->entityManager->refresh($site);

        $this->logger->notice(new Message(
            '%1$d site pages removed from "%2$s".', // @translate
            $result, $site->getSlug()
        ));
    }

    protected function removeCollecting(Site $site): void
    {
        $sql = <<<SQL
DELETE FROM `collecting_form`
WHERE `site_id` = {$site->getId()};
SQL;
        $result = $this->connection->exec($sql);

        $this->logger->notice(new Message(
            '%1$d collecting forms removed from "%2$s".', // @translate
            $result, $site->getSlug()
        ));
    }

    /**
     * Duplicate settings of a site.
     *
     * @param Site $source
     * @param Site $target
     * @param array $settings
     */
    protected function copySettings(Site $source, Site $target, array $settings = null): void
    {
        $sql = <<<SQL
INSERT INTO `site_setting` (`id`, `site_id`, `value`)
SELECT `t2`.`id`, {$target->getId()} AS 'site_id', `t2`.`value` FROM (
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
    protected function copySitePages(Site $source, Site $target, $mode): void
    {
        // Get pages to check rights.
        if (!$source->getPages()->count()) {
            return;
        }

        // Manage private page slugs.
        $sql = 'SELECT `id`, `slug` FROM `site_page` WHERE `site_id` = ' . (int) $target->getId();
        $existingSlugs = $this->connection->query($sql)->fetchAll(\PDO::FETCH_KEY_PAIR);

        /**
         * @var \Omeka\Entity\SitePage $sourcePage
         * @var \Omeka\Api\Representation\SitePageRepresentation $targetPage
         */
        foreach ($source->getPages() as $sourcePage) {
            $slug = $sourcePage->getSlug();
            $slugExists = in_array($slug, $existingSlugs);
            if ($slugExists) {
                $slug = mb_substr($slug . '_' . $source->getSlug(), 0, 190);
                if (in_array($slug, $existingSlugs)) {
                    $slug = mb_substr($slug, 0, 185) . '_' . substr(str_replace(['+', '/'], '', base64_encode(random_bytes(20))), 0, 4);
                }
            }
            if ($mode === 'mirror') {
                $targetPage = [
                    'o:title' => $sourcePage->getTitle(),
                    'o:slug' => $slug,
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
            $targetPage['o:slug'] = $slug;

            $response = $this->api->create('site_pages', $targetPage, [], ['responseContent' => 'resource', 'initialize' => false, 'finalize' => false, 'flushEntityManager' => false]);
            if ($response) {
                $targetPage = $response->getContent();
                $this->mapPages[$sourcePage->getId()] = $targetPage;
                $this->addRelations($sourcePage, $targetPage);
                if ($slugExists) {
                    $this->logger->err(new Message(
                        'The page slug "%1$s" from the source has been renamed "%2$s".', // @translate
                        $sourcePage->getSlug(), $slug
                    ));
                }
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

    protected function copySitePermissions(Site $source, Site $target): void
    {
        $sql = <<<SQL
INSERT INTO `site_permission` (`site_id`, `user_id`, `role`)
SELECT {$target->getId()} AS 'site_id', `t2`.`user_id`, `t2`.`role` FROM (
    SELECT `t`.`site_id`, `t`.`user_id`, `t`.`role` FROM `site_permission` AS `t` WHERE `site_id` = {$source->getId()}
) AS `t2`
ON DUPLICATE KEY UPDATE `site_id`={$target->getId()}, `user_id`=`t2`.`user_id`, `role`=`t2`.`role`;
SQL;
        $this->connection->exec($sql);
        $this->entityManager->refresh($target);
    }

    protected function copySiteItemPool(Site $source, Site $target): void
    {
        $target->setItemPool($source->getItemPool());
        $this->entityManager->refresh($target);
    }

    protected function copySiteItemSets(Site $source, Site $target): void
    {
        $sql = <<<SQL
INSERT INTO `site_item_set` (`site_id`, `item_set_id`, `position`)
SELECT {$target->getId()} AS 'site_id', `t2`.`item_set_id`, `t2`.`position` FROM (
    SELECT `t`.`site_id`, `t`.`item_set_id`, `t`.`position` FROM `site_item_set` AS `t` WHERE `site_id` = {$source->getId()}
) AS `t2`
ON DUPLICATE KEY UPDATE `site_id`={$target->getId()}, `item_set_id`=`t2`.`item_set_id`, `position`=`t2`.`position`;
SQL;
        $this->connection->exec($sql);
        $this->entityManager->refresh($target);
    }

    protected function copySiteTheme(Site $source, Site $target): void
    {
        // $target->setTitle($source->getTitle());
        // $target->setSummary($source->getSummary());
        // $target->setIsPublic($source->isPublic());

        $theme = $source->getTheme() ?: 'default';
        $target->setTheme($theme);

        /** @var \Omeka\Settings\SiteSettings $siteSettings */
        // The settings may be already copied with other settings.
        $siteSettings = $this->getServiceLocator()->get('Omeka\Settings\Site');
        $siteSettings->setTargetId($source->getId());
        $themeSettings = $siteSettings->get('theme_settings_' . $theme) ?: '{}';
        $siteSettings->setTargetId($target->getId());
        $siteSettings->set('theme_settings_' . $theme, $themeSettings);

        $this->entityManager->flush();
    }

    protected function copySiteNavigation(Site $source, Site $target): void
    {
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
        $iterate = function (&$navigation) use (&$iterate): void {
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

    protected function copyCollecting(Site $source, Site $target): void
    {
        $sql = <<<SQL
INSERT INTO `collecting_form` (`item_set_id`, `site_id`, `owner_id`, `label`, `anon_type`, `success_text`, `email_text`)
SELECT  `t2`.`item_set_id`, {$target->getId()} AS 'site_id', `t2`.`owner_id`, `t2`.`label`, `t2`.`anon_type`, `t2`.`success_text`, `t2`.`email_text` FROM (
    SELECT `t`.`item_set_id`, `t`.`site_id`, `t`.`owner_id`, `t`.`label`, `t`.`anon_type`, `t`.`success_text`, `t`.`email_text` FROM `collecting_form` AS `t` WHERE `site_id` = {$source->getId()}
) AS `t2`;
SQL;
        $result = $this->connection->exec($sql);

        $this->logger->notice(new Message(
            '%1$d collecting forms from site "%2$s" were successfully copied into "%3$s".', // @translate
            $result, $source->getSlug(), $target->getSlug()
        ));

        // Check if the module is the fork one with column multiple or the basic one.
        $sql = <<<'SQL'
SHOW COLUMNS FROM `collecting_prompt` LIKE 'multiple';
SQL;
        $multiple = (bool) $this->connection->exec($sql) ? ', `multiple`' : '';
        $sql = <<<SQL
INSERT INTO `collecting_prompt` (`form_id`, `property_id`, `position`, `type`, `text`, `input_type`, `select_options`, `resource_query`, `custom_vocab`, `media_type`, `required`$multiple)
SELECT `form_id`, `property_id`, `position`, `type`, `text`, `input_type`, `select_options`, `resource_query`, `custom_vocab`, `media_type`, `required`$multiple FROM `collecting_prompt`
JOIN `collecting_form` ON `collecting_form`.`id` = `collecting_prompt`.`form_id`
WHERE `collecting_form`.`site_id` = {$source->getId()};
SQL;
        $this->connection->exec($sql);

        // No need to refresh.
    }

    protected function indexPages(Site $site): void
    {
        /**
         * @var \Omeka\Stdlib\FulltextSearch $fulltext
         */
        $fulltext = $this->getServiceLocator()->get('Omeka\FulltextSearch');
        foreach ($site->getPages() as $page) {
            $fulltext->save($page, $this->pageAdapter);
        }
    }

    protected function addRelations(SitePage $sourcePage, SitePage $targetPage): void
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

    /**
     * Check if a module is active.
     *
     * @param string $module
     * @return bool
     */
    protected function isModuleActive($module)
    {
        $services = $this->getServiceLocator();
        /** @var \Omeka\Module\Manager $moduleManager */
        $moduleManager = $services->get('Omeka\ModuleManager');
        $module = $moduleManager->getModule($module);
        return $module
            && $module->getState() === \Omeka\Module\Manager::STATE_ACTIVE;
    }
}
