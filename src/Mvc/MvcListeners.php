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
            [$this, 'prepareTranslations']
        );
    }

    /**
     * Add translations from tables.
     *
     * @see \Laminas\I18n\Translator\TranslatorInterface
     * @param MvcEvent $event
     */
    public function prepareTranslations(MvcEvent $event): void
    {
        /**
         * @var \Omeka\Api\Manager $api
         * @var \Omeka\Mvc\Status $status
         * @var \Laminas\Log\Logger $logger
         * @var \Omeka\Settings\Settings $settings
         * @var \Omeka\Settings\SiteSettings $siteSettings
         * @var \Laminas\I18n\Translator\TranslatorInterface $translator
         * @var \Table\Api\Representation\TableRepresentation $table
         *
         * The current locale is set in:
         * @see \Omeka\Mvc\MvcListeners::bootstrapLocale()
         * @see \Omeka\Mvc\MvcListeners::preparePublicSite()
         *
         * @fixme Load only the current domain/language and load other ones on request. So don't preload other locales.
         * Else the translation files in another language are not loaded on demand (even if never asked).
         * It may make an issue with fallback.
         */
        $services = $event->getApplication()->getServiceManager();
        $status = $services->get('Omeka\Status');
        // The delegator from MvcTranslator and TranslatorInterface are the same.
        $translator = $services ->get(TranslatorInterface::class)->getDelegatedTranslator();
        $isSiteRequest = $status->isSiteRequest();

        $locales = [];
        if (class_exists('Table\Module', false)) {
            // Include automatic translations, from generic to specific.
            $api = $services->get('Omeka\ApiManager');
            $tableSlugs = $api->search('tables', [], ['returnScalar' => 'slug'])->getContent();
            $tables = preg_grep('~^(?:translation|translation-([a-zA-Z]{2,3})((-|_)[a-zA-Z0-9]{2,4})?)$~', $tableSlugs);
            usort($tables, fn($a, $b) => strlen($a) <=> strlen($b));
            $settings= $services->get('Omeka\Settings');
            $tables = array_merge($tables, $settings->get('internationaliation_translation_tables', []));
            if ($isSiteRequest) {
                $siteSettings = $services->get('Omeka\Settings\Site');
                $tables = array_merge($tables, $siteSettings->get('internationaliation_translation_tables', []));
            }
            if (count($tables)) {
                // TODO Maybe a direct fetch via an entity manager query?
                $tables = $api->search('tables', ['slug' => array_unique($tables)])->getContent();
                $tableSlugsNoLang = [];
                foreach ($tables as $table) {
                    $lang = $table->lang() ?: null;
                    if ($lang) {
                        $locales[$lang] = array_replace($locales[$lang] ?? [], $table->codesAssociative());
                    } else {
                        $tableSlugsNoLang[] = $table->slug();
                    }
                }
                if ($tableSlugsNoLang) {
                    $logger = $services->get('Omeka\Logger');
                    $logger->warn(
                        'The following tables are included for internationalisation, but have no defined language: {table_slugs}.', // @translate
                        ['table_slugs' => implode(', ', $tableSlugsNoLang)]
                    );
                }
            }
        }

        // The translator service for remote translator "tables" must be set,
        // whatever there are locales or not, and whatever the presence of an
        // existing one loaded during bootstrap.
        // It avoids a second issue in case of error during bootstrap, in
        // particular duiring the upgrade of Common. It allows to finish the
        // upgrade.
        // Unlike files, all locales should be loaded here.
        // If another domain is needed, it may be added as "tables_xxx".
        $translator->getPluginManager()->setService(
            'tables',
            new PhpSimpleArray(['default' => $locales])
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
