<?php declare(strict_types=1);

namespace InternationalisationTest\View\Helper;

use CommonTest\AbstractTestCase;

/**
 * The table "translated" is flat, so it cannot hold the plural forms nor the
 * contexts of gettext. The module loads the files "*.mo" of the directory
 * "files/language" for them, so these tests install one and check that the
 * translator uses it.
 */
class TranslateContextTest extends AbstractTestCase
{
    /**
     * @var string
     */
    protected $installedFile;

    public function setUp(): void
    {
        parent::setUp();

        $fixture = dirname(__DIR__, 2) . '/_files/zz.mo';
        $this->assertFileExists($fixture);

        $config = $this->getService('Config');
        $basePath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
        $dir = $basePath . '/language';
        if (!is_dir($dir) || !is_writeable($dir)) {
            $this->markTestSkipped(sprintf('The directory "%s" is not writeable.', $dir));
        }

        $this->installedFile = $dir . '/zz.mo';
        copy($fixture, $this->installedFile);
    }

    public function tearDown(): void
    {
        if ($this->installedFile && file_exists($this->installedFile)) {
            unlink($this->installedFile);
        }

        parent::tearDown();
    }

    protected function translator(): \Laminas\I18n\Translator\TranslatorInterface
    {
        return $this->getService('MvcTranslator');
    }

    /**
     * A simple string of a file "*.mo" is translated like a string of the
     * table.
     */
    public function testGettextFileIsLoaded(): void
    {
        $this->assertSame(
            'Chaine de test du module Internationalisation',
            $this->translator()->translate('Test string of the module Internationalisation', 'default', 'zz')
        );
    }

    /**
     * The plural forms cannot be stored in the table, so they come from the
     * file, with the plural rule of its header.
     */
    public function testPluralFormsAreTranslated(): void
    {
        $translator = $this->translator();
        $this->assertSame(
            'Un contenu teste',
            $translator->translatePlural('One tested item', '%d tested items', 1, 'default', 'zz')
        );
        $this->assertSame(
            '%d contenus testes',
            $translator->translatePlural('One tested item', '%d tested items', 3, 'default', 'zz')
        );
    }

    /**
     * The same string may need two translations according to its context.
     */
    public function testContextsAreTranslated(): void
    {
        $helper = $this->getViewHelper('translateContext');
        $this->assertSame('Accueil du menu teste', $helper('Tested home', 'menu', 'default', 'zz'));
        $this->assertSame('Bouton accueil teste', $helper('Tested home', 'button', 'default', 'zz'));
    }

    /**
     * Without a translation for the pair message/context, the message alone is
     * used, and never the internal key of gettext.
     */
    public function testUnknownContextFallsBackToTheMessage(): void
    {
        $helper = $this->getViewHelper('translateContext');
        $this->assertSame('Tested home', $helper('Tested home', 'unknown', 'default', 'zz'));
        $this->assertSame(
            'Chaine de test du module Internationalisation',
            $helper('Test string of the module Internationalisation', 'unknown', 'default', 'zz')
        );
        // An empty context is a simple translation.
        $this->assertSame(
            'Chaine de test du module Internationalisation',
            $helper('Test string of the module Internationalisation', '', 'default', 'zz')
        );
    }

    public function testUnknownMessageIsReturnedAsIs(): void
    {
        $helper = $this->getViewHelper('translateContext');
        $this->assertSame('Not a translated string', $helper('Not a translated string', 'menu', 'default', 'zz'));
    }
}
