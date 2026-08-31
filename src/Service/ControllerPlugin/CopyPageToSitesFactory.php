<?php declare(strict_types=1);

namespace Internationalisation\Service\ControllerPlugin;

use Internationalisation\Mvc\Controller\Plugin\CopyPageToSites;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class CopyPageToSitesFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new CopyPageToSites(
            $services->get('Omeka\ApiManager'),
            $services->get('Omeka\Connection'),
            $services->get('Omeka\Logger')
        );
    }
}
