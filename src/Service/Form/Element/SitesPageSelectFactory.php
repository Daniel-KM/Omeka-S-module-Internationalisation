<?php
namespace LanguageSwitcher\Service\Form\Element;

use Interop\Container\ContainerInterface;
use LanguageSwitcher\Form\Element\SitesPageSelect;
use Zend\ServiceManager\Factory\FactoryInterface;

class SitesPageSelectFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $element = new SitesPageSelect(null, $options);
        $element->setApiManager($services->get('Omeka\ApiManager'));
        return $element;
    }
}
