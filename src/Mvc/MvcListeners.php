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
        $translator = $services ->get(TranslatorInterface::class)->getDelegatedTranslator();
        $isSiteRequest = $status->isSiteRequest();

        if ($isSiteRequest) {
            /** @var \Omeka\Api\Representation\SiteRepresentation $site */
            $site = $services->get('ControllerPluginManager')->get('currentSite')();
            $themeLanguagePath = OMEKA_PATH . '/themes/' . $site->theme() . '/language';
            if (file_exists($themeLanguagePath) && is_dir($themeLanguagePath)) {
                $translator
                    ->addTranslationFilePattern('gettext', $themeLanguagePath, '%s.mo', 'default');
            }

            if (class_exists('Table\Module', false)) {
                $config = $services->get('Config');
                $localFilesPath = $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files');
                $localFile = $localFilesPath . '/language/table-' . $site->id() . '.php';
                if (file_exists($localFile) && is_readable($localFile)) {
                    // Method addTranslationFile() cannot be used, because the
                    // locale is unknown and may change when creating file.
                    $locales = include $localFile;
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
            $localFile = $localFilesPath . '/language/table-0.php';
            if (file_exists($localFile) && is_readable($localFile)) {
                $locales = include $localFile;
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
