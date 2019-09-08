<?php
namespace Internationalisation\Service\ViewHelper;

use Internationalisation\View\Helper\LanguageIso;
use Interop\Container\ContainerInterface;
use Zend\ServiceManager\Factory\FactoryInterface;

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
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        require_once dirname(dirname(dirname(__DIR__))) . '/vendor/daniel-km/simple-iso-639-3/src/Iso639p3.php';

        return new LanguageIso();
    }
}
