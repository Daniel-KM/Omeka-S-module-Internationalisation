<?php declare(strict_types=1);

namespace Internationalisation\Service\ControllerPlugin;

use Internationalisation\Mvc\Controller\Plugin\UpdateTranslationFiles;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class UpdateTranslationFilesFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $config = $services->get('Config');

        return new UpdateTranslationFiles(
            $services->get('Omeka\ApiManager'),
            $services->get('Omeka\Connection'),
            $services->get('Omeka\Logger'),
            $services->get('Omeka\Settings'),
            $services->get('Omeka\Settings\Site'),
            $config['file_store']['local']['base_path'] ?: (OMEKA_PATH . '/files')
        );
    }
}
