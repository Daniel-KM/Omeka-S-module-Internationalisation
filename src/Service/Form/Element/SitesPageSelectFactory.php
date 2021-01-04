<?php declare(strict_types=1);
namespace Internationalisation\Service\Form\Element;

use Internationalisation\Form\Element\SitesPageSelect;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class SitesPageSelectFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $currentSite = $services->get('ControllerPluginManager')->get('currentSite');

        $element = new SitesPageSelect(null, $options);
        $element->setApiManager($services->get('Omeka\ApiManager'));
        $element->setSite($currentSite());
        return $element;
    }
}
