<?php
namespace Internationalisation\Service\BlockLayout;

use Internationalisation\Site\BlockLayout\SimplePage;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class SimplePageFactory implements FactoryInterface
{
    /**
     * Create the SimplePage block layout service.
     *
     * @return SimplePage
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new SimplePage(
            $services->get('Omeka\ApiManager')
        );
    }
}
