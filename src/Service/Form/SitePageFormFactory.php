<?php declare(strict_types=1);
namespace Internationalisation\Service\Form;

use Internationalisation\Form\SitePageForm;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class SitePageFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new SitePageForm(null, $options);
    }
}
