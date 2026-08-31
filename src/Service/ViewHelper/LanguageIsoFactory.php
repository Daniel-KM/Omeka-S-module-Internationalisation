<?php declare(strict_types=1);

namespace Internationalisation\Service\ViewHelper;

use Internationalisation\View\Helper\LanguageIso;
use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

/**
 * Service factory to get the language switcher view helper.
 */
class LanguageIsoFactory implements FactoryInterface
{
    /**
     * Create and return the LanguageIso view helper.
     *
     * @return LanguageIso
     */
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new LanguageIso();
    }
}
