<?php declare(strict_types=1);

namespace InternationalisationTest\Controller;

use CommonTest\AbstractTestCase;
use Internationalisation\Api\Adapter\TranslatedAdapter;
use Internationalisation\Controller\Admin\TranslationController;
use Omeka\Mvc\Exception\PermissionDeniedException;
use Omeka\Permissions\Acl;

/**
 * The translations are global, so all the roles of the admin can read them,
 * but only some of them can write them.
 */
class AclTest extends AbstractTestCase
{
    /**
     * @var int|null
     */
    protected $researcherId;

    public function tearDown(): void
    {
        if ($this->researcherId) {
            $this->logout();
            $this->loginWithCredentials('admin@example.com', 'root');
            $this->getService('Omeka\ApiManager')->delete('users', $this->researcherId);
        }
        parent::tearDown();
    }

    protected function loginAsResearcher(): void
    {
        $this->loginWithCredentials('admin@example.com', 'root');
        $user = $this->getService('Omeka\ApiManager')->create('users', [
            'o:email' => 'intl-researcher-' . substr(md5((string) mt_rand()), 0, 8) . '@example.com',
            'o:name' => 'Researcher',
            'o:role' => Acl::ROLE_RESEARCHER,
            'o:is_active' => true,
        ])->getContent();
        $this->researcherId = $user->id();
        $this->loginAs($this->researcherId);
    }

    public function roleProvider(): array
    {
        return [
            [Acl::ROLE_GLOBAL_ADMIN, true],
            [Acl::ROLE_SITE_ADMIN, true],
            [Acl::ROLE_EDITOR, true],
            [Acl::ROLE_REVIEWER, false],
            [Acl::ROLE_AUTHOR, false],
            [Acl::ROLE_RESEARCHER, false],
        ];
    }

    /**
     * @dataProvider roleProvider
     */
    public function testOnlySomeRolesWriteTheTranslations(string $role, bool $canWrite): void
    {
        $acl = $this->getService('Omeka\Acl');
        foreach (['create', 'update', 'delete', 'batch_delete'] as $privilege) {
            $this->assertSame($canWrite, $acl->isAllowed($role, TranslatedAdapter::class, $privilege), "$role $privilege");
        }
        foreach (['edit', 'add-string', 'update-string', 'delete-string', 'delete', 'reindex'] as $action) {
            $this->assertSame($canWrite, $acl->isAllowed($role, TranslationController::class, $action), "$role $action");
        }
    }

    /**
     * @dataProvider roleProvider
     */
    public function testAllRolesReadTheTranslations(string $role): void
    {
        $acl = $this->getService('Omeka\Acl');
        foreach (['search', 'read'] as $privilege) {
            $this->assertTrue($acl->isAllowed($role, TranslatedAdapter::class, $privilege), "$role $privilege");
        }
        foreach (['index', 'browse', 'show', 'show-details', 'translate-page', 'copy-page'] as $action) {
            $this->assertTrue($acl->isAllowed($role, TranslationController::class, $action), "$role $action");
        }
    }

    public function testAResearcherCannotOpenTheBatchEdition(): void
    {
        $this->loginAsResearcher();
        $this->expectException(PermissionDeniedException::class);
        $this->dispatch('/admin/translation/fr/edit');
    }

    public function testAResearcherCannotAddAString(): void
    {
        $this->loginAsResearcher();
        $this->expectException(PermissionDeniedException::class);
        try {
            $this->dispatch('/admin/translation/zz/add-string', 'POST', ['string' => 'Home', 'translation' => 'Accueil']);
        } finally {
            $exists = $this->getService('Omeka\Connection')
                ->executeQuery('SELECT 1 FROM `translated` WHERE `lang` = ? AND `string` = ?', ['zz', 'Home'])
                ->fetchOne();
            $this->assertFalse($exists);
        }
    }

    public function testAResearcherSeesNoInlineEdition(): void
    {
        $this->loginAsResearcher();
        $this->dispatch('/admin/translation/fr');
        $this->assertResponseStatusCode(200);
        $content = (string) $this->getResponse()->getContent();
        $this->assertStringNotContainsString('contenteditable', $content);
        $this->assertStringNotContainsString('translated-add', $content);
        $this->assertStringNotContainsString('translated-batch', $content);
        $this->assertStringNotContainsString('<textarea', $content);
    }

    public function testAResearcherCannotPostTheBatchEdition(): void
    {
        $connection = $this->getService('Omeka\Connection');
        $connection->executeStatement('DELETE FROM `translated` WHERE `lang` = ?', ['zz']);
        $connection->executeStatement('INSERT INTO `translated` (`lang`, `string`, `translation`) VALUES (?, ?, ?)', ['zz', 'Browse', 'Parcourir']);
        $this->loginAsResearcher();
        try {
            $this->dispatch('/admin/translation/zz', 'POST', ['translations' => 'Browse = Naviguer']);
            $this->assertResponseStatusCode(302);
            $messages = $this->getApplicationServiceLocator()->get('ControllerPluginManager')->get('messenger')->get();
            $this->assertContains('You are not allowed to update translations.', array_map('strval', $messages[\Omeka\Mvc\Controller\Plugin\Messenger::ERROR] ?? []));
            $translation = $this->getService('Omeka\Connection')
                ->executeQuery('SELECT `translation` FROM `translated` WHERE `lang` = ? AND `string` = ?', ['zz', 'Browse'])
                ->fetchOne();
            $this->assertSame('Parcourir', $translation);
        } finally {
            $this->getService('Omeka\Connection')->executeStatement('DELETE FROM `translated` WHERE `lang` = ?', ['zz']);
        }
    }
}
