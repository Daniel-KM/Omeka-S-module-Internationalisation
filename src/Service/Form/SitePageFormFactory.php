<?php
namespace LanguageSwitcher\Service\Form;

use Interop\Container\ContainerInterface;
use LanguageSwitcher\Form\SitePageForm;
use Zend\ServiceManager\Factory\FactoryInterface;

class SitePageFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new SitePageForm(null, $options);
    }
}
