<?php declare(strict_types=1);

namespace Internationalisation\Mvc;

use Internationalisation\Translator\Loader\PhpSimpleArray;
use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
use Laminas\I18n\Translator\TranslatorInterface;
use Laminas\Mvc\MvcEvent;

class MvcListeners extends AbstractListenerAggregate
{
    public function attach(EventManagerInterface $events, $priority = 1): void
    {
        $this->listeners[] = $events->attach(
            MvcEvent::EVENT_ROUTE,
            [$this, 'includeTranslations']
        );
    }

    /**
     * Add translations from theme and tables.
     */
    public function includeTranslations(MvcEvent $event): void
    {
        /**
         * @var \Omeka\Mvc\Status $status
         * @var \Laminas\I18n\Translator\Translator $translator
         *
         * The current locale is set in:
         * @see \Omeka\Mvc\MvcListeners::bootstrapLocale()
         * @see \Omeka\Mvc\MvcListeners::preparePublicSite()
         */
        $services = $event->getApplication()->getServiceManager();
        $status = $services->get('Omeka\Status');
        // The delegator from MvcTranslator and TranslatorInterface are the same.
        $translator = $services->get(TranslatorInterface::class)->getDelegatedTranslator();
        $isSiteRequest = $status->isSiteRequest();

        if ($isSiteRequest) {
            /** @var \Omeka\Api\Representation\SiteRepresentation $site */
            $site = $services->get('ControllerPluginManager')->get('currentSite')();
            $themeLanguagePath = OMEKA_PATH . '/themes/' . $site->theme() . '/language';
            if (file_exists($themeLanguagePath) && is_dir($themeLanguagePath)) {
                // Since version 4.0, the theme language is loaded automatically
                // when theme.ini has the option "has_translation" set to true.
                if (version_compare(\Omeka\Module::VERSION, '4.0.0', '<')) {
                    $translator
                        ->addTranslationFilePattern('gettext', $themeLanguagePath, '%s.mo', 'default')
                        ->addTranslationFilePattern('phpArray', $themeLanguagePath, '%s.php', 'default');
                } else {
                    $theme = $site->theme() ?: 'default';
                    $themeManager = $services->get('Omeka\Site\ThemeManager');
                    $currentTheme = $themeManager->getTheme($theme);
                    if ($currentTheme && $currentTheme->getIni('has_translations')) {
                        $translator
                            // Already added via MvcListeners.
                            // ->addTranslationFilePattern('gettext', $themeLanguagePath, '%s.mo', 'default')
                            ->addTranslationFilePattern('phpArray', $themeLanguagePath, '%s.php', 'default');
                    }
                }
            }

            if (class_exists('Table\Module', false)) {
                $config = $services->get('Config');
                $localFilesPath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
                $languageDir = $localFilesPath . '/language';
                $localFile = $languageDir . '/table-' . $site->id() . '.php';
                // Security: validate path is within expected directory.
                $realPath = realpath($localFile);
                $realLanguageDir = realpath($languageDir);
                if ($realPath
                    && $realLanguageDir
                    && strpos($realPath, $realLanguageDir . DIRECTORY_SEPARATOR) === 0
                    && is_readable($realPath)
                ) {
                    // Method addTranslationFile() cannot be used, because the
                    // locale is unknown and may change when creating file.
                    $locales = include $realPath;
                    if (is_array($locales)) {
                        $translator
                            // ->addTranslationFile('tables', $localFile, null, null)
                            ->addRemoteTranslations('tables')
                            ->getPluginManager()->setService(
                                'tables',
                                new PhpSimpleArray(['default' => $locales])
                            );
                    }
                }
            }
        } elseif (class_exists('Table\Module', false)) {
            $config = $services->get('Config');
            $localFilesPath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
            $languageDir = $localFilesPath . '/language';
            $localFile = $languageDir . '/table-0.php';
            // Security: validate path is within expected directory.
            $realPath = realpath($localFile);
            $realLanguageDir = realpath($languageDir);
            if ($realPath
                && $realLanguageDir
                && strpos($realPath, $realLanguageDir . DIRECTORY_SEPARATOR) === 0
                && is_readable($realPath)
            ) {
                $locales = include $realPath;
                if (is_array($locales)) {
                    $translator
                        ->addRemoteTranslations('tables')
                        ->getPluginManager()->setService(
                            'tables',
                            new PhpSimpleArray(['default' => $locales])
                        );
                }
            }
        }
    }
}
