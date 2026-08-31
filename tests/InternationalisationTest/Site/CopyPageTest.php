<?php declare(strict_types=1);

namespace InternationalisationTest\Site;

use CommonTest\AbstractTestCase;
use Doctrine\DBAL\ParameterType;

/**
 * A page can be copied into the other sites of its group, so a translation can
 * be prepared without duplicating the whole site.
 *
 * The copy keeps the blocks of the original page and is related to it, so the
 * module Translator can translate it in place afterwards.
 */
class CopyPageTest extends AbstractTestCase
{
    const TITLE = 'Titre de la page à copier';
    const HTML = '<p>Un texte à copier.</p>';

    /**
     * @var array
     */
    protected $siteIds = [];

    /**
     * @var array
     */
    protected $slugs = [];

    /**
     * @var int
     */
    protected $pageId;

    /**
     * @var mixed
     */
    protected $previousGroups;

    public function setUp(): void
    {
        parent::setUp();

        $auth = $this->getService('Omeka\AuthenticationService');
        $adapter = $auth->getAdapter();
        $adapter->setIdentity('admin@example.com');
        $adapter->setCredential('root');
        $auth->authenticate();

        $api = $this->getService('Omeka\ApiManager');
        $settings = $this->getService('Omeka\Settings');

        $suffix = 'intl-copy-' . substr(md5((string) mt_rand()), 0, 8);
        foreach (['fr', 'en', 'pl'] as $lang) {
            $slug = $suffix . '-' . $lang;
            $site = $api->create('sites', [
                'o:slug' => $slug,
                'o:title' => 'Site ' . $lang,
                'o:theme' => 'default',
            ])->getContent();
            $this->siteIds[$lang] = $site->id();
            $this->slugs[$lang] = $slug;
        }

        $group = array_values($this->slugs);
        $this->previousGroups = $settings->get('internationalisation_site_groups');
        $groups = [];
        foreach ($group as $slug) {
            $groups[$slug] = $group;
        }
        $settings->set('internationalisation_site_groups', $groups);

        $this->pageId = $api->create('site_pages', [
            'o:site' => ['o:id' => $this->siteIds['fr']],
            'o:slug' => 'page-source',
            'o:title' => self::TITLE,
            'o:block' => [
                ['o:layout' => 'pageTitle', 'o:data' => []],
                ['o:layout' => 'html', 'o:data' => ['html' => self::HTML]],
            ],
        ])->getContent()->id();
    }

    public function tearDown(): void
    {
        $api = $this->getService('Omeka\ApiManager');
        foreach ($this->siteIds as $id) {
            try {
                $api->delete('sites', $id);
            } catch (\Throwable $e) {
            }
        }
        $this->siteIds = [];

        $settings = $this->getService('Omeka\Settings');
        $this->previousGroups === null
            ? $settings->delete('internationalisation_site_groups')
            : $settings->set('internationalisation_site_groups', $this->previousGroups);

        parent::tearDown();
    }

    protected function connection(): \Doctrine\DBAL\Connection
    {
        return $this->getService('Omeka\Connection');
    }

    /**
     * Copy the page like the action of the sidebar of the browse does.
     */
    protected function copyInto(array $langs): array
    {
        $copyPageToSites = $this->getControllerPlugin('copyPageToSites');

        return $copyPageToSites(
            $this->pageId,
            array_map(fn ($lang) => $this->siteIds[$lang], $langs)
        );
    }

    /**
     * The creation of a site adds a page "welcome", so it is excluded here to
     * count only the pages that were copied.
     */
    protected function pagesOfSite(int $siteId): array
    {
        return $this->connection()->executeQuery(
            'SELECT id, slug, title FROM site_page WHERE site_id = :id AND slug != :welcome ORDER BY id',
            ['id' => $siteId, 'welcome' => 'welcome'],
            ['id' => ParameterType::INTEGER, 'welcome' => ParameterType::STRING]
        )->fetchAllAssociative();
    }

    protected function relatedPageIds(): array
    {
        $rows = $this->connection()->executeQuery(
            'SELECT page_id, related_page_id FROM site_page_relation'
            . ' WHERE page_id = :id OR related_page_id = :id',
            ['id' => $this->pageId],
            ['id' => ParameterType::INTEGER]
        )->fetchAllAssociative();

        $result = [];
        foreach ($rows as $row) {
            $result[] = (int) $row['page_id'] === $this->pageId
                ? (int) $row['related_page_id']
                : (int) $row['page_id'];
        }
        sort($result);

        return $result;
    }

    public function testPageIsCopiedWithItsBlocks(): void
    {
        $this->assertSame([], $this->pagesOfSite($this->siteIds['en']));

        $this->copyInto(['en']);

        $pages = $this->pagesOfSite($this->siteIds['en']);
        $this->assertCount(1, $pages);
        $this->assertSame('page-source', $pages[0]['slug']);
        $this->assertSame(self::TITLE, $pages[0]['title']);

        $blocks = $this->connection()->executeQuery(
            'SELECT layout, data FROM site_page_block WHERE page_id = :id ORDER BY position',
            ['id' => (int) $pages[0]['id']],
            ['id' => ParameterType::INTEGER]
        )->fetchAllAssociative();
        $this->assertCount(2, $blocks);
        $this->assertSame('pageTitle', $blocks[0]['layout']);
        $this->assertSame('html', $blocks[1]['layout']);
        $this->assertSame(['html' => self::HTML], json_decode((string) $blocks[1]['data'], true));
    }

    public function testCopyIsRelatedToTheOriginal(): void
    {
        $this->copyInto(['en']);

        $pages = $this->pagesOfSite($this->siteIds['en']);
        $this->assertSame([(int) $pages[0]['id']], $this->relatedPageIds());
    }

    public function testSeveralSitesAtOnce(): void
    {
        $this->copyInto(['en', 'pl']);

        $this->assertCount(1, $this->pagesOfSite($this->siteIds['en']));
        $this->assertCount(1, $this->pagesOfSite($this->siteIds['pl']));
        $this->assertCount(2, $this->relatedPageIds());
    }

    /**
     * A site that has already a related page keeps it: the translation is not
     * duplicated.
     */
    public function testSiteWithRelationIsSkipped(): void
    {
        $this->copyInto(['en']);
        $first = $this->relatedPageIds();

        $this->copyInto(['en']);

        $this->assertCount(1, $this->pagesOfSite($this->siteIds['en']));
        $this->assertSame($first, $this->relatedPageIds());
    }

    /**
     * The slug of the original page may be used in the target site already.
     */
    public function testSlugConflictIsSolved(): void
    {
        $this->getService('Omeka\ApiManager')->create('site_pages', [
            'o:site' => ['o:id' => $this->siteIds['en']],
            'o:slug' => 'page-source',
            'o:title' => 'Another page',
        ]);

        $this->copyInto(['en']);

        $pages = $this->pagesOfSite($this->siteIds['en']);
        $this->assertCount(2, $pages);
        $slugs = array_column($pages, 'slug');
        $this->assertContains('page-source', $slugs);
        $this->assertContains('page-source-2', $slugs);
    }

    public function testNoSiteSelectedCopiesNothing(): void
    {
        $this->copyInto([]);

        $this->assertSame([], $this->pagesOfSite($this->siteIds['en']));
        $this->assertSame([], $this->relatedPageIds());
    }

    /**
     * The page is never copied into its own site.
     */
    public function testDuplicateInTheSameSite(): void
    {
        $result = $this->copyInto(['fr']);

        // A duplicate is not a translation, so it is returned apart.
        $this->assertSame([], $result['created']);
        $this->assertCount(1, $result['duplicated']);

        // The original page and its duplicate, with a number in the slug.
        $pages = $this->pagesOfSite($this->siteIds['fr']);
        $this->assertCount(2, $pages);
        $this->assertSame(['page-source', 'page-source-2'], array_column($pages, 'slug'));

        // A duplicate is not a translation of the original page.
        $this->assertSame([], $this->relatedPageIds());
    }

    /**
     * The number of the slug follows the pages that exist already.
     */
    public function testDuplicateTwiceInTheSameSite(): void
    {
        $this->copyInto(['fr']);
        $this->copyInto(['fr']);

        $pages = $this->pagesOfSite($this->siteIds['fr']);
        $this->assertSame(
            ['page-source', 'page-source-2', 'page-source-3'],
            array_column($pages, 'slug')
        );
        $this->assertSame([], $this->relatedPageIds());
    }

    /**
     * A duplicate and a translation can be created in a single request.
     */
    public function testDuplicateAndTranslateAtOnce(): void
    {
        $result = $this->copyInto(['fr', 'en']);

        $this->assertCount(1, $result['created']);
        $this->assertCount(1, $result['duplicated']);
        $this->assertCount(2, $this->pagesOfSite($this->siteIds['fr']));

        // Only the page of the other site is a translation.
        $enPages = $this->pagesOfSite($this->siteIds['en']);
        $this->assertCount(1, $enPages);
        $this->assertSame([(int) $enPages[0]['id']], $this->relatedPageIds());
    }

    /**
     * The page is copied into all the sites, with or without a group.
     */
    public function testSitesAreListedWithoutGroup(): void
    {
        $this->getService('Omeka\Settings')->delete('internationalisation_site_groups');

        $result = $this->copyInto(['en']);

        $this->assertCount(1, $result['created']);
        $this->assertCount(1, $this->pagesOfSite($this->siteIds['en']));
    }

    /**
     * The result tells what was done, so the action can report it.
     */
    public function testResultReportsTheSkippedSites(): void
    {
        $first = $this->copyInto(['en']);
        $this->assertCount(1, $first['created']);
        $this->assertSame([], $first['skipped']);

        $second = $this->copyInto(['en']);
        $this->assertSame([], $second['created']);
        $this->assertSame([$this->siteIds['en']], $second['skipped']);
    }
}
