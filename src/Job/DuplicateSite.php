<?php declare(strict_types=1);

namespace Internationalisation\Job;

use Doctrine\DBAL\ParameterType;
use Internationalisation\Entity\SitePageRelation;
use Omeka\Entity\Site;
use Omeka\Entity\SitePage;
use Omeka\Job\AbstractJob;
use Omeka\Mvc\Exception\NotFoundException;

class DuplicateSite extends AbstractJob
{
    /**
     * @var \Omeka\Api\Manager
     */
    protected $api;

    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $connection;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entityManager;

    /**
     * @var \Laminas\Log\Logger
     */
    protected $logger;

    /**
     * @var \Omeka\Api\Adapter\SitePageAdapter
     */
    protected $pageAdapter;

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
            $this->logger->err(
                'The site #{site_id} is not available for copy. Check your rights.', // @translate
                ['site_id' => $targetId]
            );
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
                $this->logger->err(
                    'The site #{site_id} cannot be copied. Check your rights.', // @translate
                    ['site_id' => $sourceId]
                );
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
            $this->removeSettings($target);
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
            $this->copySettings($source, $target);
            $settings = $this->getArg('settings', []);
            $this->updateSettings($target, $settings);
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

        $this->indexPages($target);
        // Assign resources and reindex pages.
        $services->get(\Omeka\Job\Dispatcher::class)->dispatch(
            \Omeka\Job\UpdateSiteItems::class,
            [
                'sites' => [$targetId => $target->getItemPool()],
                'action' => 'add',
            ],
            $services->get(\Omeka\Job\DispatchStrategy\Synchronous::class)
        );

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

    protected function removeSettings(Site $site): void
    {
        $sql = <<<SQL
            DELETE FROM `site_setting`
            WHERE `site_id` = {$site->getId()};
            SQL;
        $this->connection->executeStatement($sql);

        $this->logger->notice(
            'Site settings of "{site_slug}" successfully removed.', // @translate
            ['site_slug' => $site->getSlug()]
        );
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
        $result = $this->connection->executeStatement($sql);
        $this->entityManager->refresh($site);

        $this->logger->notice(
            '{total} site permissions removed from "{site_slug}".', // @translate
            ['total' => $result, 'site_slug' => $site->getSlug()]
        );
    }

    protected function removeSiteItemSets(Site $site): void
    {
        $sql = <<<SQL
            DELETE FROM `site_item_set`
            WHERE `site_id` = {$site->getId()};
            SQL;
        $this->connection->executeStatement($sql);
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
        $this->connection->executeStatement($sql);
        $sql = <<<SQL
            DELETE `site_page_block` FROM `site_page_block`
            INNER JOIN `site_page` ON `site_page`.`id` = `site_page_block`.`page_id`
            WHERE `site_page`.`site_id` = {$site->getId()};
            SQL;
        $this->connection->executeStatement($sql);
        $sql = <<<SQL
            DELETE FROM `site_page`
            WHERE `site_id` = {$site->getId()};
            SQL;
        $result = $this->connection->executeStatement($sql);
        $this->entityManager->refresh($site);

        $this->logger->notice(
            '{total} site pages removed from "{site_slug}".', // @translate
            ['total' => $result, 'site_slug' => $site->getSlug()]
        );
    }

    protected function removeCollecting(Site $site): void
    {
        $sql = <<<SQL
            DELETE FROM `collecting_form`
            WHERE `site_id` = {$site->getId()};
            SQL;
        $result = $this->connection->executeStatement($sql);

        $this->logger->notice(
            '{total} collecting forms removed from "{site_slug}".', // @translate
            ['total' => $result, 'site_slug' => $site->getSlug()]
        );
    }

    /**
     * Duplicate settings of a site.
     */
    protected function copySettings(Site $source, Site $target): void
    {
        $sql = <<<SQL
            INSERT INTO `site_setting` (`id`, `site_id`, `value`)
            SELECT `t2`.`id`, {$target->getId()} AS 'site_id', `t2`.`value` FROM (
                SELECT `t`.`id`, `t`.`value` FROM `site_setting` AS `t` WHERE `site_id` = {$source->getId()}
            ) AS `t2`
            ON DUPLICATE KEY UPDATE
                `id` = `t2`.`id`,
                `site_id` = {$target->getId()},
                `value` = `t2`.`value`;
            SQL;
        $this->connection->executeStatement($sql);

        $this->logger->notice(
            'Site settings of "{site_slug}" successfully copied into "{site_slug_2}".', // @translate
            ['site_slug' => $source->getSlug(), 'site_slug_2' => $target->getSlug()]
        );
    }

    protected function updateSettings(Site $target, array $settings): void
    {
        // Don't use the sservice siteSetting, because other parts use direct
        // sql and the action may not update any settings.

        if (!$settings) {
            return;
        }

        // Most of the time, there is only one setting (locale).

        $siteIdTarget = (int) $target->getId();
        foreach ($settings as $id => $value) {
            $sql = <<<SQL
                INSERT INTO `site_setting` (`id`, `site_id`, `value`)
                VALUES (:id, :site_id, :value)
                ON DUPLICATE KEY UPDATE
                    `id` = :id,
                    `site_id` = :site_id,
                    `value` = :value;
                SQL;
            $this->connection->executeStatement(
                $sql,
                ['id' => $id, 'site_id' => $siteIdTarget, 'value' => json_encode($value)],
                ['id' => ParameterType::STRING, 'site_id' => ParameterType::INTEGER, 'value' => ParameterType::STRING]
            );
        }
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
        $existingSlugs = $this->connection->executeQuery($sql)->fetchAllKeyValue();

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
                    $slug = mb_substr($slug, 0, 185) . '_' . substr(strtr(base64_encode(random_bytes(128)), ['+' => '', '/' => '', '=' => '']), 0, 4);
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
                // TODO Don't use json_decode(json_encode()).
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
                    $this->logger->err(
                        'The page slug "{page_slug}" from the source has been renamed "{slug}".', // @translate
                        ['page_slug' => $sourcePage->getSlug(), 'slug' => $slug]
                    );
                }
            } else {
                $this->logger->err(
                    'Unable to copy page "{page_slug}" of site "{site_slug}". The page is skipped.', // @translate
                    ['page_slug' => $sourcePage->getSlug(), 'site_slug' => $source->getSlug()]
                );
                $this->job->setStatus(\Omeka\Entity\Job::STATUS_ERROR);
            }
        }

        $this->entityManager->flush();

        $this->logger->notice(
            '{total} site pages of "{site_slug}" successfully copied into "{site_slug_2}" (mode "{mode}").', // @translate
            ['total' => count($this->mapPages), 'site_slug' => $source->getSlug(), 'site_slug_2' => $target->getSlug(), 'mode' => $mode]
        );
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
        $this->connection->executeStatement($sql);
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
        $this->connection->executeStatement($sql);
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
        $themeSettings = $siteSettings->get('theme_settings_' . $theme);
        $siteSettings->setTargetId($target->getId());
        if ($themeSettings) {
            $siteSettings->set('theme_settings_' . $theme, $themeSettings);
        }
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
        $iterate = null;
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
        try {
            $result = $this->connection->executeStatement($sql);
        } catch (\Exception $e) {
            $this->logger->notice(
                'The module Collecting is a new version and is not copiable for now. Copy forms manually if needed.' // @translate
            );
            return;
        }

        $this->logger->notice(
            '{total} collecting forms from site "{site_slug}" were successfully copied into "{site_slug_2}".', // @translate
            ['total' => $result, 'site_slug' => $source->getSlug(), 'site_slug_2' => $target->getSlug()]
        );

        // Check if the module is the fork one with column multiple or the basic one.
        $sql = <<<'SQL'
            SHOW COLUMNS FROM `collecting_prompt` LIKE 'multiple';
            SQL;
        try {
            $multiple = $this->connection->executeStatement($sql) ? ', `multiple`' : '';
        } catch (\Exception $e) {
            $multiple = '';
        }
        $sql = <<<SQL
            INSERT INTO `collecting_prompt` (`form_id`, `property_id`, `position`, `type`, `text`, `input_type`, `select_options`, `resource_query`, `custom_vocab`, `media_type`, `required`$multiple)
            SELECT `form_id`, `property_id`, `position`, `type`, `text`, `input_type`, `select_options`, `resource_query`, `custom_vocab`, `media_type`, `required`$multiple FROM `collecting_prompt`
            JOIN `collecting_form` ON `collecting_form`.`id` = `collecting_prompt`.`form_id`
            WHERE `collecting_form`.`site_id` = {$source->getId()};
            SQL;
        try {
            $result = $this->connection->executeStatement($sql);
        } catch (\Exception $e) {
            $this->logger->notice(
                'The module Collecting is a new version and is not copiable for now. Copy forms manually if needed.' // @translate
            );
            return;
        }

        // No need to refresh.
    }

    protected function indexPages(Site $site): void
    {
        /**
         * @var \Omeka\Stdlib\FulltextSearch $fulltext
         * @var \Omeka\Entity\SitePage $page
         */
        $fulltext = $this->getServiceLocator()->get('Omeka\FulltextSearch');
        foreach ($site->getPages() as $page) {
            try {
                $fulltext->save($page, $this->pageAdapter);
            } catch (\Exception $e) {
                // Some blocks fail without a site, that may not be provided for
                // background tasks.
                $this->logger->warn(
                    'The full text for page {page_slug} was not saved. Run indexation of full text manually in main settings or in tasks of Easy Admin. Exception: {exception}', // @translate
                    ['page_slug' => $page->getSlug(), 'exception' => $e]
                );
            }
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
