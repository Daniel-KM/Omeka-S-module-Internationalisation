<?php declare(strict_types=1);
namespace Internationalisation\Service\Form;

use Internationalisation\Form\SiteSettingsFieldset;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

/**
 * Service factory to get the SiteSettingFieldset.
 */
class SiteSettingsFieldsetFactory implements FactoryInterface
{
    /**
     * Create and return the SiteSettingFieldset.
     *
     * @return \Internationalisation\Form\SiteSettingsFieldset
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $fieldset = new SiteSettingsFieldset(null, $options);
        $fieldset->setSiteSetting($services->get('ViewHelperManager')->get('siteSetting'));
        return $fieldset;
    }
}
