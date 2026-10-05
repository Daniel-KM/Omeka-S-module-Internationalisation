<?php declare(strict_types=1);

namespace InternationalisationTest\Controller;

use CommonTest\AbstractTestCase;
use Internationalisation\Stdlib\TranslatedEditor;

/**
 * The page of a language allows to edit each string and each translation
 * inline, and to delete a row, without leaving the page.
 *
 * The rules are checked on the editor itself: the token of the form is bound
 * to the session, that a dispatch of the tests cannot carry from a request to
 * the next one, so the controller is only checked for what it guarantees
 * without a valid token.
 */
class InlineEditTest extends AbstractTestCase
{
    /**
     * @var string
     */
    protected $language = 'zz';

    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $connection;

    /**
     * @var \Internationalisation\Stdlib\TranslatedEditor
     */
    protected $editor;

    public function setUp(): void
    {
        parent::setUp();

        $auth = $this->getService('Omeka\AuthenticationService');
        $adapter = $auth->getAdapter();
        $adapter->setIdentity('admin@example.com');
        $adapter->setCredential('root');
        $auth->authenticate();

        $this->connection = $this->getService('Omeka\Connection');
        $this->editor = new TranslatedEditor($this->connection);

        $this->removeTranslations();
        foreach (['Browse' => 'Parcourir', 'Search' => 'Rechercher'] as $string => $translation) {
            $this->connection->executeStatement(
                'INSERT INTO `translated` (`lang`, `string`, `translation`) VALUES (:lang, :string, :translation)',
                ['lang' => $this->language, 'string' => $string, 'translation' => $translation]
            );
        }
    }

    public function tearDown(): void
    {
        $this->removeTranslations();
        parent::tearDown();
    }

    protected function removeTranslations(): void
    {
        $this->connection->executeStatement(
            'DELETE FROM `translated` WHERE `lang` = :lang',
            ['lang' => $this->language]
        );
    }

    protected function translationOf(string $string): ?string
    {
        return $this->editor->translationOf($this->language, $string);
    }

    public function testThePageOfALanguageIsEditableInline(): void
    {
        $this->dispatch('/admin/translation/' . $this->language);
        $this->assertResponseStatusCode(200);
        $content = $this->getResponse()->getContent();
        $this->assertStringContainsString('contenteditable="true"', $content);
        $this->assertStringContainsString('role="textbox"', $content);
        $this->assertStringContainsString('aria-describedby="translated-inline-hint"', $content);
        $this->assertStringContainsString('translate_string_csrf', $content);
        $this->assertStringContainsString('role="status"', $content);
    }

    /**
     * Render the page with the given rights, whatever the role of the user.
     *
     * The module allows the adapter to all the default roles, so a role is not
     * enough to check that the view really follows the rights.
     */
    protected function renderShowWithRights(bool $canUpdate, bool $canDelete, bool $canCreate = false): string
    {
        $services = $this->getServiceManager();
        $viewHelpers = $services->get('ViewHelperManager');
        $viewHelpers->setAllowOverride(true);
        $helper = new class extends \Laminas\View\Helper\AbstractHelper {
            public $rights = [];

            public function __invoke($resource = null, $privilege = null)
            {
                return !empty($this->rights[$privilege]);
            }
        };
        $helper->rights = ['update' => $canUpdate, 'delete' => $canDelete, 'create' => $canCreate];
        $viewHelpers->setService('userIsAllowed', $helper);

        return $services->get('ViewRenderer')->render('internationalisation/admin/translation/show', [
            'language' => $this->language,
            'localeName' => 'Test',
            'translations' => ['Browse' => 'Parcourir'],
            'confirmForm' => $services->get('FormElementManager')->get(\Omeka\Form\ConfirmForm::class),
            'csrf' => new \Laminas\Form\Element\Csrf('translate_string_csrf'),
            'form' => $services->get('FormElementManager')->get(\Internationalisation\Form\TranslationForm::class),
            'batchOpen' => false,
        ]);
    }

    public function testNothingIsEditableWithoutTheRightToUpdate(): void
    {
        $html = $this->renderShowWithRights(false, true);
        $this->assertStringNotContainsString('contenteditable', $html);
        $this->assertStringNotContainsString('translated-inline-hint', $html);
        $this->assertStringNotContainsString('data-update-url', $html);
        $this->assertStringNotContainsString('translated-editable', $html);
        // The deletion remains available on its own.
        $this->assertStringContainsString('delete-string', $html);
        $this->assertStringContainsString('translated-delete', $html);
    }

    public function testNoActionIsDisplayedWithoutAnyRight(): void
    {
        $html = $this->renderShowWithRights(false, false);
        $this->assertStringNotContainsString('contenteditable', $html);
        $this->assertStringNotContainsString('data-update-url', $html);
        $this->assertStringNotContainsString('delete-string', $html);
        $this->assertStringNotContainsString('translated-inline', $html);
        // No empty list of actions is left in the cells.
        $this->assertDoesNotMatchRegularExpression('~<ul class="actions">\s*</ul>~', $html);
    }

    public function testOnlyTheDeletionIsHiddenWithoutTheRightToDelete(): void
    {
        $html = $this->renderShowWithRights(true, false);
        $this->assertStringContainsString('contenteditable="true"', $html);
        $this->assertStringContainsString('data-update-url', $html);
        $this->assertStringNotContainsString('delete-string', $html);
        $this->assertStringNotContainsString('translated-delete', $html);
    }

    public function testAStringCanBeAddedWithTheRightToCreate(): void
    {
        $html = $this->renderShowWithRights(false, false, true);
        $this->assertStringContainsString('translated-add', $html);
        $this->assertStringContainsString('data-add-url', $html);
        $this->assertStringContainsString('<template id="translated-new-row">', $html);
        // The new row is editable to be filled, but not the existing ones.
        $this->assertSame(2, substr_count($html, 'contenteditable="true"'));
        $this->assertStringContainsString('translated-inline-hint', $html);
    }

    public function testNoStringCanBeAddedWithoutTheRightToCreate(): void
    {
        $html = $this->renderShowWithRights(true, true, false);
        $this->assertStringNotContainsString('translated-add', $html);
        $this->assertStringNotContainsString('data-add-url', $html);
        $this->assertStringNotContainsString('translated-new-row', $html);
    }

    public function testTheBatchEditionIsToggledWithTheRightToUpdate(): void
    {
        $html = $this->renderShowWithRights(true, false);
        $this->assertStringContainsString('translated-batch-toggle', $html);
        $this->assertStringContainsString('aria-pressed="false"', $html);
        $this->assertStringContainsString('id="translated-batch"', $html);
        $this->assertStringContainsString('<textarea', $html);
    }

    public function testTheBatchEditionIsMissingWithoutTheRightToUpdate(): void
    {
        $html = $this->renderShowWithRights(false, true, true);
        $this->assertStringNotContainsString('translated-batch', $html);
        $this->assertStringNotContainsString('<textarea', $html);
    }

    public function testTheOldPageOfBatchEditionOpensThePage(): void
    {
        $this->dispatch('/admin/translation/' . $this->language . '/edit');
        $this->assertRedirectRegex('~/admin/translation/' . $this->language . '\?batch=1$~');
    }

    public function testTheBatchEditionIsOpenedByTheQuery(): void
    {
        $this->dispatch('/admin/translation/' . $this->language . '?batch=1');
        $this->assertResponseStatusCode(200);
        $content = (string) $this->getResponse()->getContent();
        $this->assertStringContainsString('aria-pressed="true"', $content);
        $this->assertStringContainsString('<div id="item-metadata" hidden>', $content);
        $this->assertStringContainsString('Parcourir', $content);
    }

    public function testTheTranslationsAreReplaced(): void
    {
        $this->editor->replace($this->language, [
            // Updated.
            'Browse' => 'Naviguer',
            // Added.
            'Home' => 'Accueil',
            // "Search" is removed.
        ]);
        $this->assertSame('Naviguer', $this->translationOf('Browse'));
        $this->assertSame('Accueil', $this->translationOf('Home'));
        $this->assertNull($this->translationOf('Search'));
    }

    public function testAnEmptyBatchRemovesAllTheTranslations(): void
    {
        $this->editor->replace($this->language, []);
        $this->assertNull($this->translationOf('Browse'));
        $this->assertNull($this->translationOf('Search'));
    }

    public function testAForgedRequestIsRefused(): void
    {
        $this->dispatch('/admin/translation/' . $this->language . '/update-string', 'POST', [
            'translate_string_csrf' => 'forged',
            'string' => 'Browse',
            'field' => 'translation',
            'text' => 'Naviguer',
        ]);
        $result = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertSame('fail', $result['status'] ?? null);
        $this->assertSame('Parcourir', $this->translationOf('Browse'));
    }

    public function testAGetRequestIsRefused(): void
    {
        $this->dispatch('/admin/translation/' . $this->language . '/update-string');
        $result = (array) json_decode($this->getResponse()->getContent(), true);
        $this->assertSame('fail', $result['status'] ?? null);
        $this->assertSame('Parcourir', $this->translationOf('Browse'));
    }

    public function testATranslationIsUpdated(): void
    {
        $data = $this->editor->update($this->language, 'Browse', 'translation', 'Naviguer');
        $this->assertSame(['string' => 'Browse', 'translation' => 'Naviguer'], $data);
        $this->assertSame('Naviguer', $this->translationOf('Browse'));
    }

    public function testAStringIsRenamed(): void
    {
        $data = $this->editor->update($this->language, 'Browse', 'string', 'Browse all');
        $this->assertSame(['string' => 'Browse all', 'translation' => 'Parcourir'], $data);
        $this->assertNull($this->translationOf('Browse'));
        $this->assertSame('Parcourir', $this->translationOf('Browse all'));
    }

    public function testAStringIsNotRenamedIntoAnExistingOne(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('This string is already translated in this language.');
        try {
            $this->editor->update($this->language, 'Browse', 'string', 'Search');
        } finally {
            $this->assertSame('Parcourir', $this->translationOf('Browse'));
            $this->assertSame('Rechercher', $this->translationOf('Search'));
        }
    }

    public function testAnEmptyValueIsRefused(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->editor->update($this->language, 'Browse', 'translation', '');
    }

    public function testAnUnknownFieldIsRefused(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->editor->update($this->language, 'Browse', 'lang', 'fr');
    }

    public function testAnUnknownStringIsRefused(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->editor->update($this->language, 'Missing', 'translation', 'Absent');
    }

    public function testAStringIsAdded(): void
    {
        $data = $this->editor->create($this->language, 'Home', 'Accueil');
        $this->assertSame(['string' => 'Home', 'translation' => 'Accueil'], $data);
        $this->assertSame('Accueil', $this->translationOf('Home'));
    }

    public function testAnExistingStringIsNotAdded(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('This string is already translated in this language.');
        try {
            $this->editor->create($this->language, 'Browse', 'Naviguer');
        } finally {
            $this->assertSame('Parcourir', $this->translationOf('Browse'));
        }
    }

    public function testAnIncompleteStringIsNotAdded(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->editor->create($this->language, 'Home', '');
    }

    public function testAStringIsDeleted(): void
    {
        $data = $this->editor->delete($this->language, 'Browse');
        $this->assertSame(['string' => 'Browse'], $data);
        $this->assertNull($this->translationOf('Browse'));
        $this->assertSame('Rechercher', $this->translationOf('Search'));
    }

    public function testAnUnknownStringIsNotDeleted(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->editor->delete($this->language, 'Missing');
    }
}
