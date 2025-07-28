<?php declare(strict_types=1);

namespace Internationalisation\Mvc;

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
            [$this, 'prepareTranslations']
        );
    }

    /**
     * Add theme translations.
     *
     * @see \Laminas\I18n\Translator\TranslatorInterface
     * @param MvcEvent $event
     */
    public function prepareTranslations(MvcEvent $event): void
    {
        /**
         * @var \Omeka\Mvc\Status $status
         * @var \Omeka\Api\Manager $apiManager
         * @var \Omeka\Settings\Settings $settings
         * @var \Omeka\Settings\SiteSettings $siteSettings
         * @var \Laminas\I18n\Translator\TranslatorInterface $translator
         * @var \Table\Api\Representation\TableRepresentation $table
         */
        $services = $event->getApplication()->getServiceManager();
        $status = $services->get('Omeka\Status');
        // The delegator from MvcTranslator and TranslatorInterface are the same.
        $translator = $services ->get(TranslatorInterface::class)->getDelegatedTranslator();
        $isSiteRequest = $status->isSiteRequest();

        $locales = [];
        if (class_exists('Table\Module', false)) {
            $settings= $services->get('Omeka\Settings');
            $tables = $settings->get('internationaliation_translation_tables', []);
            if ($isSiteRequest) {
                $siteSettings = $services->get('Omeka\Settings\Site');
                $tables = array_merge($tables, $siteSettings->get('internationaliation_translation_tables', []));
            }
            if (count($tables)) {
                $api = $services->get('Omeka\ApiManager');
                // TODO Module Table does not support querying multiple slugs for now.
                foreach (array_unique($tables) as $table) {
                    try {
                        $table = $api->read('tables', ['slug' => $table])->getContent();
                    } catch (\Omeka\Api\Exception\NotFoundException $e) {
                        continue;
                    }
                    $lang = $table->lang() ?: null;
                    if (!$lang) {
                        continue;
                    }
                    $locales[$lang] = array_replace($locales[$lang] ?? [], $table->codesAssociative());
                }
            }
        }

        // The translator service for remote translator "tables" must be set,
        // whatever there are locales or not.
        $translator->getPluginManager()->setService(
            'tables',
            new \Laminas\I18n\Translator\Loader\PhpMemoryArray(['default' => $locales])
        );

        if ($isSiteRequest) {
            /** @var \Omeka\Api\Representation\SiteRepresentation $currentSIte */
            $currentSite = $services->get('ControllerPluginManager')->get('currentSite');
            $themeLanguagePath = OMEKA_PATH . '/themes/' . $currentSite()->theme() . '/language';
            if (file_exists($themeLanguagePath) && is_dir($themeLanguagePath)) {
                $translator
                    ->addTranslationFilePattern('gettext', $themeLanguagePath, '%s.mo', 'default');
            }
        }
    }
}
