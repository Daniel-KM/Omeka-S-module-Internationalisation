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
     * @var \Internationalisation\View\Helper\LanguageList
     */
    protected $languageList;

    public function __construct(LanguageList $languageList)
    {
        $this->languageList = $languageList;
    }

    /**
     * Render the language switcher.
     *
     * @param array|string|null $options If a string, this is the name of the
     * view script, or a view model. In array, this is the key 'template'.
     * Possible options:
     * - template (string)
     * - displayLocale: "code" (the 2 or 3 letters language code, default) or "flag".
     * Other options are passed to the template.
     * @return string
     */
    public function __invoke($options = null)
    {
        $site = $this->currentSite();
        $data = $this->languageList->currentPage();

        if (empty($options) || is_string($options)) {
            $options = ['template' => $options, 'displayLocale' => 'code'];
        } else {
            $options += ['template' => null, 'displayLocale' => 'code'];
        }

        $template = $options['template'] ?: self::PARTIAL_NAME;
        unset($options['template']);

        return $this->view->partial($template, [
                'site' => $site,
                'locales' => $data,
                'localeLabels' => $this->languageList->__invoke('locale_labels'),
        ] + $options);
    }

    protected function currentSite(): ?\Omeka\Api\Representation\SiteRepresentation
    {
        static $site;
        if (!$site) {
            $vars = $this->view->vars();
            $site = $vars->offsetGet('site');
            if (!$site) {
                $site = $this->view
                    ->getHelperPluginManager()
                    ->get('Laminas\View\Helper\ViewModel')
                    ->getRoot()
                    ->getVariable('site');
                $vars->offsetSet('site', $site);
            }
        }
        return $site;
    }
}
