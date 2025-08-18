<?php declare(strict_types=1);

namespace Internationalisation\View\Helper;

use Laminas\View\Helper\AbstractHelper;

/**
 * View helper for rendering the language switcher.
 */
class LanguageSwitcher extends AbstractHelper
{
    /**
     * The default partial view script.
     */
    const PARTIAL_NAME = 'common/language-switcher';

    /**
     * @var string
     */
    protected $defaultLocale;

    /**
     * @var \Internationalisation\View\Helper\LanguageList
     */
    protected $languageList;

    public function __construct(
        LanguageList $languageList,
        string $defaultLocale
    ) {
        $this->defaultLocale = $defaultLocale;
        $this->languageList = $languageList;
    }

    /**
     * Render the language switcher.
     *
     * @param array $options Possible options:
     * - template (string)
     * - displayLocale: "code" (the 2 or 3 letters language code, default) or "flag".
     * Other options are passed to the template.
     * @return string
     */
    public function __invoke($options = []): string
    {
        $site = $this->currentSite();
        $data = $this->languageList->currentPage();

        // TODO Remove support of very old themes.
        if (empty($options) || is_string($options)) {
            $options = ['template' => $options, 'displayLocale' => 'code'];
        } else {
            $options += ['template' => null, 'displayLocale' => 'code'];
        }

        $template = $options['template'] ?: self::PARTIAL_NAME;
        unset($options['template']);

        return $this->view->partial($template, [
                'site' => $site,
                'defaultLocale' => $this->defaultLocale,
                'locales' => $data,
                'localeLabels' => $this->languageList->__invoke('locale_labels'),
        ] + $options);
    }

    /**
     * Get the current site from the view or the root view (main layout).
     */
    protected function currentSite(): ?\Omeka\Api\Representation\SiteRepresentation
    {
        return $this->view->site ?? $this->view->site = $this->view
            ->getHelperPluginManager()
            ->get('Laminas\View\Helper\ViewModel')
            ->getRoot()
            ->getVariable('site');
    }
}
