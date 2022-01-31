<?php declare(strict_types=1);

namespace Internationalisation\Service\ViewHelper;

use Internationalisation\View\Helper\LanguageSwitcher;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

/**
 * Service factory to get the language switcher view helper.
 */
class LanguageSwitcherFactory implements FactoryInterface
{
    /**
     * Create and return the LanguageSwitcher view helper.
     *
     * @return LanguageSwitcher
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new LanguageSwitcher(
            $services->get('ViewHelperManager')->get('languageList')
        );
    }
}
