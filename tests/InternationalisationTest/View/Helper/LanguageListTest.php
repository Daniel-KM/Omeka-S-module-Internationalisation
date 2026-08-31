<?php declare(strict_types=1);

namespace InternationalisationTest\View\Helper;

use CommonTest\AbstractTestCase;
use Internationalisation\View\Helper\LanguageList;

class LanguageListTest extends AbstractTestCase
{
    protected function helper(): LanguageList
    {
        return $this->getViewHelper('languageList');
    }

    public function testInvokeWithoutArgumentReturnsTheHelper(): void
    {
        $helper = $this->helper();
        $this->assertSame($helper, $helper());
    }

    public function testInvokeWithUnknownTypeReturnsNull(): void
    {
        $this->assertNull($this->helper()('unknown_type'));
    }

    public function testListsAreArrays(): void
    {
        $helper = $this->helper();
        foreach (['locale_sites', 'locale_labels', 'site_groups'] as $type) {
            $this->assertIsArray($helper($type), $type);
        }
    }

    /**
     * The locales are read from the site settings, keyed by site slug, and the
     * empty ones are filtered by the factory.
     */
    public function testLocaleSitesAreKeyedBySlugWithoutEmptyLocale(): void
    {
        $localeSites = $this->helper()('locale_sites');
        // The install used for the tests may have no site with a locale.
        $this->assertIsArray($localeSites);
        foreach ($localeSites as $slug => $localeId) {
            $this->assertIsString($slug);
            $this->assertNotSame('', $slug);
            $this->assertIsString($localeId);
            $this->assertNotSame('', $localeId);
        }
    }

    /**
     * A label is prepared for each locale in use, so the keys of the labels are
     * exactly the locales of the sites.
     */
    public function testLocaleLabelsCoverEveryLocaleInUse(): void
    {
        $helper = $this->helper();
        $localeSites = $helper('locale_sites');
        $localeLabels = $helper('locale_labels');
        foreach ($localeSites as $localeId) {
            $this->assertArrayHasKey($localeId, $localeLabels, $localeId);
            $this->assertNotSame('', $localeLabels[$localeId], $localeId);
        }
        $this->assertSame(
            array_values(array_unique(array_values($localeSites))),
            array_values(array_unique(array_keys($localeLabels)))
        );
    }

    /**
     * currentPage() needs a site in the request, so outside a site it returns
     * an empty array instead of failing. It used to emit a warning on
     * array_flip().
     */
    public function testCurrentPageIsEmptyOutsideASite(): void
    {
        $this->assertSame([], $this->helper()('current_page'));
    }
}
