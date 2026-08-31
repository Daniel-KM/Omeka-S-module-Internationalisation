<?php declare(strict_types=1);

namespace Internationalisation\Service\Controller;

use Internationalisation\Controller\Admin\TranslationController;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Omeka\Module\Manager as ModuleManager;
use Psr\Container\ContainerInterface;

class TranslationControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        // The module Translator is optional: when it is active, the copies of a
        // page may be translated in the background.
        $module = $services->get('Omeka\ModuleManager')->getModule('Translator');
        $hasTranslator = $module
            && $module->getState() === ModuleManager::STATE_ACTIVE;

        return new TranslationController(
            $services->get('Omeka\Connection'),
            $services->get(\Omeka\Job\Dispatcher::class),
            $hasTranslator
        );
    }
}
