<?php declare(strict_types=1);

namespace Internationalisation\Service\Controller;

use Internationalisation\Controller\Admin\TranslationController;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class TranslationControllerFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new TranslationController(
            $services->get('Omeka\Connection')
        );
    }
}
