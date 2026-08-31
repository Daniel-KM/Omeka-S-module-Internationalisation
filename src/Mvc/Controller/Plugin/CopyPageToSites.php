<?php declare(strict_types=1);

namespace Internationalisation\Mvc\Controller\Plugin;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Omeka\Api\Manager as ApiManager;

/**
 * Copy a site page into other sites and relate the copies as translations.
 *
 * The copy contains the same blocks than the original page, like the job
 * DuplicateSite does for a whole site, so the module Translator can translate
 * them in place afterwards.
 *
 * A site that has already a page related to the original one is skipped: the
 * translation exists, so it should not be duplicated.
 */
class CopyPageToSites extends AbstractPlugin
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
     * @var \Laminas\Log\Logger
     */
    protected $logger;

    public function __construct(ApiManager $api, Connection $connection, $logger)
    {
        $this->api = $api;
        $this->connection = $connection;
        $this->logger = $logger;
    }

    /**
     * Copy a page into a list of sites and relate the copies to it.
     *
     * The page may be copied into its own site: it is then a duplicate, not a
     * translation, so it is not related to the original page, and the site is
     * never skipped, unlike a site that has already a translation.
     *
     * @param int $pageId The page to copy.
     * @param int[] $siteIds The sites to copy the page into, including the site
     * of the page itself.
     * @return array Result with the keys "created" (ids of the new pages),
     * "skipped" (ids of the sites that have already a translation) and "errors"
     * (ids of the sites where the copy failed).
     */
    public function __invoke(int $pageId, array $siteIds): array
    {
        $result = ['created' => [], 'skipped' => [], 'errors' => []];

        try {
            /** @var \Omeka\Api\Representation\SitePageRepresentation $page */
            $page = $this->api->read('site_pages', $pageId)->getContent();
        } catch (\Throwable $e) {
            return $result;
        }

        $siteIds = array_values(array_unique(array_filter(array_map('intval', $siteIds))));
        if (!$siteIds) {
            return $result;
        }

        // A duplicate in the own site of the page is always allowed, so it is
        // set apart from the sites that may have a translation already.
        $ownSiteId = (int) $page->site()->id();
        $isDuplicate = in_array($ownSiteId, $siteIds, true);
        $siteIds = array_values(array_diff($siteIds, [$ownSiteId]));

        $sitesWithTranslation = $this->sitesWithTranslation($pageId);
        $result['skipped'] = array_values(array_intersect($siteIds, $sitesWithTranslation));
        $siteIds = array_values(array_diff($siteIds, $sitesWithTranslation));
        if ($isDuplicate) {
            array_unshift($siteIds, $ownSiteId);
        }
        if (!$siteIds) {
            return $result;
        }

        // Use json_encode() instead of jsonSerialize() to get only arrays.
        // @see \Internationalisation\Job\DuplicateSite::copySitePages()
        $data = json_decode(json_encode($page), true);
        unset($data['o:id']);
        // The relations are managed below, not by the api of the new page.
        unset($data['o-module-internationalisation:related_page']);

        $toRelate = [];
        foreach ($siteIds as $siteId) {
            $copy = $data;
            $copy['o:site'] = ['o:id' => $siteId];
            $copy['o:slug'] = $this->uniqueSlug($page->slug(), $siteId);

            try {
                $newPageId = $this->api->create('site_pages', $copy)->getContent()->id();
                $result['created'][] = $newPageId;
                // A duplicate in the same site is not a translation: the
                // language switcher displays one related page by site.
                if ($siteId !== $ownSiteId) {
                    $toRelate[] = $newPageId;
                }
            } catch (\Throwable $e) {
                $result['errors'][] = $siteId;
                $this->logger->err(
                    'Unable to copy the page "{page_slug}" into the site #{site_id}: {message}', // @translate
                    ['page_slug' => $page->slug(), 'site_id' => $siteId, 'message' => $e->getMessage()]
                );
            }
        }

        if ($toRelate) {
            $this->relatePages($pageId, $toRelate);
        }

        return $result;
    }

    /**
     * Get the ids of the sites that have already a page related to a page.
     *
     * @return int[]
     */
    protected function sitesWithTranslation(int $pageId): array
    {
        $sql = <<<'SQL'
            SELECT DISTINCT p.site_id
            FROM site_page_relation r
            INNER JOIN site_page p
                ON p.id = IF(r.page_id = :page_id, r.related_page_id, r.page_id)
            WHERE r.page_id = :page_id OR r.related_page_id = :page_id
            SQL;

        return array_map('intval', $this->connection->executeQuery(
            $sql,
            ['page_id' => $pageId],
            ['page_id' => ParameterType::INTEGER]
        )->fetchFirstColumn());
    }

    /**
     * Relate a page and its copies, and all the pages already related to it.
     *
     * The relations are stored as unordered pairs, so all the pages of a group
     * are related together.
     */
    protected function relatePages(int $pageId, array $newPageIds): void
    {
        $ids = array_merge([$pageId], $newPageIds, $this->relatedPages($pageId));
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        sort($ids);

        $values = [];
        $params = [];
        $types = [];
        $i = 0;
        foreach ($ids as $id) {
            foreach ($ids as $relatedId) {
                if ($relatedId > $id) {
                    $values[] = "(:p{$i}, :r{$i})";
                    $params["p{$i}"] = $id;
                    $params["r{$i}"] = $relatedId;
                    $types["p{$i}"] = ParameterType::INTEGER;
                    $types["r{$i}"] = ParameterType::INTEGER;
                    ++$i;
                }
            }
        }
        if (!$values) {
            return;
        }

        $this->connection->executeStatement(
            'INSERT INTO site_page_relation (page_id, related_page_id) VALUES '
                . implode(', ', $values)
                . ' ON DUPLICATE KEY UPDATE `id` = `id`',
            $params,
            $types
        );
    }

    /**
     * @return int[]
     */
    protected function relatedPages(int $pageId): array
    {
        $sql = 'SELECT IF(page_id = :page_id, related_page_id, page_id)'
            . ' FROM site_page_relation'
            . ' WHERE page_id = :page_id OR related_page_id = :page_id';

        return array_map('intval', $this->connection->executeQuery(
            $sql,
            ['page_id' => $pageId],
            ['page_id' => ParameterType::INTEGER]
        )->fetchFirstColumn());
    }

    /**
     * Get a slug available in a site, appending a suffix when needed.
     */
    protected function uniqueSlug(string $slug, int $siteId): string
    {
        $existing = array_flip($this->connection->executeQuery(
            'SELECT slug FROM site_page WHERE site_id = :site_id',
            ['site_id' => $siteId],
            ['site_id' => ParameterType::INTEGER]
        )->fetchFirstColumn());

        if (!isset($existing[$slug])) {
            return $slug;
        }

        for ($i = 2; $i < 1000; $i++) {
            $candidate = mb_substr($slug, 0, 186) . '-' . $i;
            if (!isset($existing[$candidate])) {
                return $candidate;
            }
        }

        return mb_substr($slug, 0, 185) . '-' . substr(bin2hex(random_bytes(4)), 0, 4);
    }
}
