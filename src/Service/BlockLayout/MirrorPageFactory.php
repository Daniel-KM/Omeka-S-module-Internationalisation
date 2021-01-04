<?php declare(strict_types=1);

namespace Internationalisation\Service\BlockLayout;

use Internationalisation\Site\BlockLayout\MirrorPage;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class MirrorPageFactory implements FactoryInterface
{
    /**
     * Create the SimplePage block layout service.
     *
     * @return MirrorPage
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new MirrorPage(
            $services->get('Omeka\ApiManager')
        );
    }
}
