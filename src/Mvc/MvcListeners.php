<?php declare(strict_types=1);
namespace Internationalisation\Mvc;

use Laminas\EventManager\AbstractListenerAggregate;
use Laminas\EventManager\EventManagerInterface;
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
        $services = $event->getApplication()->getServiceManager();
        if (!$services->get('Omeka\Status')->isSiteRequest()) {
            return;
        }

        /** @var \Omeka\Api\Representation\SiteRepresentation $currentSIte */
        $currentSite = $services->get('ControllerPluginManager')->get('currentSite');
        $themeLanguagePath = OMEKA_PATH . '/themes/' . $currentSite()->theme() . '/language';
        if (!file_exists($themeLanguagePath) || !is_dir($themeLanguagePath)) {
            return;
        }

        $services->get('MvcTranslator')->getDelegatedTranslator()
            ->addTranslationFilePattern('gettext', $themeLanguagePath, '%s.mo', 'default');
    }
}
