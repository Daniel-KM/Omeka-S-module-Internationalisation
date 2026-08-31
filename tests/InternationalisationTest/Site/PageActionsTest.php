<?php declare(strict_types=1);

namespace InternationalisationTest\Site;

use CommonTest\AbstractTestCase;
use Doctrine\DBAL\ParameterType;

/**
 * The browse of the pages of a site gets two actions: copy the page into other
 * sites, and translate it. Both open a sidebar of the module.
 *
 * The action to translate and the checkbox of the sidebar depend on the module
 * Translator, that is optional.
 */
class PageActionsTest extends AbstractTestCase
{
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
     * @var int
     */
    protected $relatedPageId;

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
        $siteSettings = $this->getService('Omeka\Settings\Site');
        $settings = $this->getService('Omeka\Settings');

        $suffix = 'intl-actions-' . substr(md5((string) mt_rand()), 0, 8);
        foreach (['fr' => 'fr', 'en' => 'en-gb'] as $key => $locale) {
            $slug = $suffix . '-' . $key;
            $site = $api->create('sites', [
                'o:slug' => $slug,
                'o:title' => 'Site ' . $key,
                'o:theme' => 'default',
            ])->getContent();
            $this->siteIds[$key] = $site->id();
            $this->slugs[$key] = $slug;
            $siteSettings->set('locale', $locale, $site->id());
        }

        $group = array_values($this->slugs);
        $this->previousGroups = $settings->get('internationalisation_site_groups');
        $settings->set('internationalisation_site_groups', [
            $this->slugs['fr'] => $group,
            $this->slugs['en'] => $group,
        ]);

        $this->pageId = $api->create('site_pages', [
            'o:site' => ['o:id' => $this->siteIds['fr']],
            'o:slug' => 'page-source',
            'o:title' => 'Titre de la page',
            'o:block' => [['o:layout' => 'html', 'o:data' => ['html' => '<p>Un texte.</p>']]],
        ])->getContent()->id();

        $this->relatedPageId = $api->create('site_pages', [
            'o:site' => ['o:id' => $this->siteIds['en']],
            'o:slug' => 'page-source',
            'o:title' => 'Titre de la page',
            'o:block' => [['o:layout' => 'html', 'o:data' => ['html' => '<p>Un texte.</p>']]],
        ])->getContent()->id();

        $this->createRelationTable();
        $this->relatePages($this->pageId, $this->relatedPageId);
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
     * @see \Internationalisation\Entity\SitePageRelation
     */
    protected function createRelationTable(): void
    {
        $this->connection()->executeStatement(<<<'SQL'
            CREATE TABLE IF NOT EXISTS `site_page_relation` (
                `id` INT AUTO_INCREMENT NOT NULL,
                `page_id` INT NOT NULL,
                `related_page_id` INT NOT NULL,
                UNIQUE INDEX `idx_site_page_relation` (`page_id`, `related_page_id`),
                PRIMARY KEY(`id`)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB
            SQL);
    }

    protected function relatePages(int $pageId, int $relatedPageId): void
    {
        [$a, $b] = $pageId < $relatedPageId ? [$pageId, $relatedPageId] : [$relatedPageId, $pageId];
        $this->connection()->executeStatement(
            'INSERT INTO site_page_relation (page_id, related_page_id) VALUES (:a, :b)'
                . ' ON DUPLICATE KEY UPDATE `id` = `id`',
            ['a' => $a, 'b' => $b],
            ['a' => ParameterType::INTEGER, 'b' => ParameterType::INTEGER]
        );
    }

    protected function page(int $pageId)
    {
        return $this->getService('Omeka\ApiManager')->read('site_pages', $pageId)->getContent();
    }

    /**
     * Render the actions added to a row of the browse of the pages.
     */
    protected function browseActions(int $pageId): string
    {
        $view = $this->getService('ViewRenderer');
        $module = $this->getService('ModuleManager')->getModule('Internationalisation');

        $event = new \Laminas\EventManager\Event(
            'view.browse.actions',
            $view,
            ['resource' => $this->page($pageId)]
        );

        ob_start();
        $module->handleViewBrowseActionsPage($event);

        return (string) ob_get_clean();
    }

    protected function controller(): \Internationalisation\Controller\Admin\TranslationController
    {
        return $this->getService('ControllerManager')
            ->get(\Internationalisation\Controller\Admin\TranslationController::class);
    }

    /**
     * Call a protected method of the controller.
     */
    protected function invoke(string $method, array $args = [])
    {
        $controller = $this->controller();
        $reflection = new \ReflectionMethod($controller, $method);
        $reflection->setAccessible(true);

        return $reflection->invokeArgs($controller, $args);
    }

    protected function hasTranslator(): bool
    {
        $controller = $this->controller();
        $property = new \ReflectionProperty($controller, 'hasTranslator');
        $property->setAccessible(true);

        return (bool) $property->getValue($controller);
    }

    public function testBrowseHasTheActionToCopy(): void
    {
        $html = $this->browseActions($this->pageId);

        $this->assertStringContainsString('fa-copy', $html);
        $this->assertStringContainsString(
            '/admin/translation/copy-page/' . $this->pageId,
            html_entity_decode($html)
        );
    }

    /**
     * The action to translate is added only with the module Translator.
     */
    public function testBrowseHasTheActionToTranslate(): void
    {
        $html = $this->browseActions($this->pageId);

        if (!$this->hasTranslator()) {
            $this->assertStringNotContainsString('fa-language', $html);
            $this->markTestSkipped('The module Translator is not active.');
        }

        $this->assertStringContainsString('fa-language', $html);
        $this->assertStringContainsString(
            '/admin/translation/translate-page/' . $this->pageId,
            html_entity_decode($html)
        );

        // The translation comes first, then the copy.
        $this->assertLessThan(
            strpos($html, 'fa-copy'),
            strpos($html, 'fa-language')
        );
    }

    public function testBrowseHasNoActionWithoutRight(): void
    {
        $this->getService('Omeka\AuthenticationService')->clearIdentity();

        $this->assertSame('', $this->browseActions($this->pageId));
    }

    /**
     * The sites of the group are listed, without the site of the page itself.
     */
    public function testSitesToCopyExcludeTheOwnSite(): void
    {
        $sites = $this->invoke('listSitesToCopy', [$this->page($this->pageId)]);

        $this->assertArrayNotHasKey($this->siteIds['fr'], $sites);
        $this->assertArrayHasKey($this->siteIds['en'], $sites);
        $this->assertSame($this->slugs['en'], $sites[$this->siteIds['en']]['slug']);
    }

    /**
     * The sites to translate into begin with the site of the page: it is
     * translated in place, unlike the related pages of the other sites.
     */
    public function testSitesToTranslateBeginWithTheOwnSite(): void
    {
        $sites = $this->invoke('listSitesToTranslate', [$this->page($this->pageId)]);

        $this->assertSame(
            [$this->siteIds['fr'], $this->siteIds['en']],
            array_keys($sites)
        );
        $this->assertTrue($sites[$this->siteIds['fr']]['own']);
        $this->assertFalse($sites[$this->siteIds['en']]['own']);
    }

    /**
     * The language of the site of the page is excluded: a page is not
     * translated into the language it is written in.
     */
    public function testLanguagesExcludeTheOneOfThePage(): void
    {
        $languages = $this->invoke('listSiteLanguages', [$this->page($this->pageId)]);

        $this->assertArrayNotHasKey('fr', $languages);
        $this->assertArrayHasKey('en', $languages);
    }

    public function testRelatedPagesAreListed(): void
    {
        $related = $this->invoke('listRelatedPages', [$this->pageId]);

        $this->assertCount(1, $related);
        $this->assertSame($this->relatedPageId, $related[0]->id());
    }

    /**
     * A group of sites is proposed to copy a page into all of them at once.
     */
    public function testSiteGroupsToCopy(): void
    {
        $groups = $this->invoke('listSiteGroupsToCopy', [$this->page($this->pageId)]);

        $this->assertArrayHasKey($this->slugs['fr'], $groups);
        $this->assertSame([$this->slugs['en']], array_values($groups[$this->slugs['fr']]));
    }

    /**
     * The post of the sidebar accepts a group as well as single sites.
     */
    public function testSiteIdsFromPost(): void
    {
        $this->assertSame(
            [$this->siteIds['en']],
            $this->invoke('siteIdsFromPost', [[(string) $this->siteIds['en']]])
        );

        $this->assertSame(
            [$this->siteIds['fr'], $this->siteIds['en']],
            $this->invoke('siteIdsFromPost', [['group:' . $this->slugs['fr']]])
        );
    }
}
