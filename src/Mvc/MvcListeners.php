<?php
namespace Internationalisation\Mvc;

use Zend\EventManager\AbstractListenerAggregate;
use Zend\EventManager\EventManagerInterface;
use Zend\Mvc\MvcEvent;

class MvcListeners extends AbstractListenerAggregate
{
    public function attach(EventManagerInterface $events, $priority = 1)
    {
        $this->listeners[] = $events->attach(
            MvcEvent::EVENT_ROUTE,
            [$this, 'prepareTranslations']
        );
    }

    /**
     * Add theme translations.
     *
     * @param MvcEvent $event
     */
    public function prepareTranslations(MvcEvent $event)
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

        /** @var \Zend\I18n\Translator\TranslatorInterface $translator */
        $services->get('MvcTranslator')->getDelegatedTranslator()
            ->addTranslationFilePattern('gettext', $themeLanguagePath, '%s.mo', 'default');
    }
}
