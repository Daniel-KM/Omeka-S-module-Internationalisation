<?php declare(strict_types=1);
namespace Internationalisation\Service\ViewHelper;

use Internationalisation\View\Helper\LocaleValue;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

/**
 * Service factory to get the locale value view helper.
 */
class LocaleValueFactory implements FactoryInterface
{
    /**
     * Create and return the LocaleValue view helper.
     *
     * @return LocaleValue
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $settings = $services->get('Omeka\Settings\Site');

        $locale = $settings->get('locale');

        $displayValues = $settings->get('internationalisation_display_values', 'all');
        if ($displayValues === 'site_iso') {
            $fallbacks = $settings->get('internationalisation_iso_codes', []);
        } elseif ($displayValues === 'site_fallback') {
            $fallbacks = $settings->get('internationalisation_fallbacks', []);
        } else {
            $fallbacks = [];
        }

        return new LocaleValue(
            $locale,
            $fallbacks
        );
    }
}
