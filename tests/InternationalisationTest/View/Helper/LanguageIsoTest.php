<?php declare(strict_types=1);

namespace InternationalisationTest\View\Helper;

use CommonTest\AbstractTestCase;
use Internationalisation\View\Helper\LanguageIso;

/**
 * The helper delegates to the library daniel-km/simple-iso-639-3, so the tests
 * check the delegation and the contract of each method, in particular that
 * code() returns the shortest code and code3letters() the canonical one.
 */
class LanguageIsoTest extends AbstractTestCase
{
    protected function helper(): LanguageIso
    {
        return $this->getViewHelper('languageIso');
    }

    /**
     * The library is loaded by the autoloader of the module, or of any other
     * module that provides it. Requiring it by path would try to redeclare its
     * classes when another module loaded it from another path.
     */
    public function testLibraryIsAutoloaded(): void
    {
        $this->assertTrue(class_exists(\Iso639p3\Iso639p3::class));
        $this->assertTrue(class_exists(\Iso639p3\Language::class));
    }

    public function testInvokeWithoutArgumentReturnsTheHelper(): void
    {
        $helper = $this->helper();
        $this->assertSame($helper, $helper());
    }

    public function testInvokeWithLanguageReturnsTheShortestCode(): void
    {
        $helper = $this->helper();
        $this->assertSame('fr', $helper('fre'));
        $this->assertSame('', $helper('fxxx'));
    }

    /**
     * code() returns the two letters code when the language has one, else the
     * three letters one, so it can be used in a html attribute "lang".
     */
    public function testCodeReturnsTheShortestCode(): void
    {
        $helper = $this->helper();
        $this->assertSame('fr', $helper->code('fr'));
        $this->assertSame('fr', $helper->code('fra'));
        // The bibliographic code is normalized.
        $this->assertSame('fr', $helper->code('fre'));
        $this->assertSame('nl', $helper->code('dut'));
        $this->assertSame('eu', $helper->code('baq'));
        // A locale and a IETF language tag are accepted.
        $this->assertSame('zh', $helper->code('zh_TW'));
        $this->assertSame('en', $helper->code('en_US'));
        $this->assertSame('fr', $helper->code('fr-CA'));
        // Without a two letters code, the three letters one is returned.
        $this->assertSame('apy', $helper->code('apy'));
        $this->assertSame('aaa', $helper->code('aaa'));
    }

    public function testCode3LettersReturnsTheCanonicalCode(): void
    {
        $helper = $this->helper();
        $this->assertSame('fra', $helper->code3letters('fr'));
        $this->assertSame('fra', $helper->code3letters('fre'));
        $this->assertSame('zho', $helper->code3letters('zh_TW'));
        $this->assertSame('eng', $helper->code3letters('en_US'));
        $this->assertSame('apy', $helper->code3letters('apy'));
    }

    public function testCode2LettersIsEmptyWithoutATwoLettersCode(): void
    {
        $helper = $this->helper();
        $this->assertSame('fr', $helper->code2letters('fre'));
        $this->assertSame('nl', $helper->code2letters('dut'));
        $this->assertSame('', $helper->code2letters('apy'));
        $this->assertSame('', $helper->code2letters('aaa'));
    }

    public function testCodesReturnsTheTwoLettersCodeFirst(): void
    {
        $helper = $this->helper();
        $this->assertSame(['fr', 'fra', 'fre'], $helper->codes('fr_FR'));
        $this->assertSame(['zh', 'chi', 'zho'], $helper->codes('zh_TW'));
        $this->assertSame(['pl', 'pol'], $helper->codes('pl'));
        $this->assertSame(['apy'], $helper->codes('apy'));
    }

    public function testNames(): void
    {
        $helper = $this->helper();
        // The native names keep the case of the source.
        $this->assertSame('Français', $helper->name('fr'));
        $this->assertSame('French', $helper->englishName('fre'));
        $this->assertSame('Chinese', $helper->englishName('zh_TW'));
        $this->assertSame('French', $helper->englishInvertedName('fra'));
        // The inverted name is the one of the reference name, not of an
        // alternate name: "Dutch", not "Flemish".
        $this->assertSame('Dutch', $helper->englishInvertedName('nld'));
        $this->assertSame('français', $helper->frenchName('fr'));
        $this->assertSame('français', $helper->frenchInvertedName('fr'));
        $this->assertSame('néerlandais', $helper->frenchName('dut'));
    }

    /**
     * Most of the languages have no native name in the standard sources, so an
     * empty string is expected, and no warning "Undefined array key".
     */
    public function testNamesAreEmptyWhenUnavailable(): void
    {
        $helper = $this->helper();
        $this->assertSame('Ghotuo', $helper->englishName('aaa'));
        $this->assertSame('', $helper->name('aaa'));
        $this->assertSame('', $helper->frenchName('aaa'));
        $this->assertSame('', $helper->frenchInvertedName('aaa'));
    }

    public function testEveryMethodIsEmptyWhenTheLanguageIsUnknown(): void
    {
        $helper = $this->helper();
        foreach (['', 'fxxx', 'NotALanguage'] as $language) {
            $message = var_export($language, true);
            $this->assertSame('', $helper->code($language), $message);
            $this->assertSame('', $helper->code3letters($language), $message);
            $this->assertSame('', $helper->code2letters($language), $message);
            $this->assertSame([], $helper->codes($language), $message);
            $this->assertSame('', $helper->name($language), $message);
            $this->assertSame('', $helper->englishName($language), $message);
            $this->assertSame('', $helper->englishInvertedName($language), $message);
            $this->assertSame('', $helper->frenchName($language), $message);
            $this->assertSame('', $helper->frenchInvertedName($language), $message);
        }
    }

    /**
     * The locales of the sites are passed to the helper as they are stored, so
     * they may be empty, or hold a region with "_" or "-".
     */
    public function testLocalesOfSitesEmitNoWarning(): void
    {
        $helper = $this->helper();
        $locales = $this->getService('Omeka\Connection')->fetchFirstColumn(
            "SELECT value FROM site_setting WHERE id = 'locale'"
        );
        // Always check a few locales, even on an install without any site.
        $locales = array_merge($locales, ['', 'fr', 'en_US', 'zh_TW', 'pt-BR']);
        foreach ($locales as $locale) {
            $locale = trim((string) $locale, '"');
            $methods = ['code', 'code3letters', 'code2letters', 'name',
                'englishName', 'englishInvertedName', 'frenchName', 'frenchInvertedName'];
            foreach ($methods as $method) {
                $this->assertIsString($helper->$method($locale), "$method($locale)");
            }
            $this->assertIsArray($helper->codes($locale), "codes($locale)");
        }
    }
}
