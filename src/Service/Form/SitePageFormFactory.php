<?php
namespace Internationalisation\Service\Form;

use Internationalisation\Form\SitePageForm;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

class SitePageFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new SitePageForm(null, $options);
    }
}
