<?php declare(strict_types=1);

namespace Internationalisation\Service\ViewHelper;

use Internationalisation\View\Helper\TranslateContext;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Psr\Container\ContainerInterface;

class TranslateContextFactory implements FactoryInterface
{
    /**
     * Create and return the TranslateContext view helper.
     *
     * @return TranslateContext
     */
    public function __invoke(ContainerInterface $services, $requestedName, ?array $options = null)
    {
        return new TranslateContext(
            $services->get('MvcTranslator')
        );
    }
}
