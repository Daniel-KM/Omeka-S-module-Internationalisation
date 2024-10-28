<?php declare(strict_types=1);
namespace Internationalisation\Service\Form;

use Internationalisation\Form\SitePageForm;
use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;

class SitePageFormFactory implements FactoryInterface
{
    /**
     * Override site page form factory, that does not trigger any events.
     * @see \Omeka\Form\SitePageForm
     * @see \Omeka\Service\Form\SitePageFormFactory
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        /** @var \Omeka\Site\Theme\Theme $theme */
        $theme = $services->get('Omeka\Site\ThemeManager')->getCurrentTheme();

        $form = new SitePageForm(null, $options ?? []);
        $form->setEventManager($services->get('EventManager'));
        $form->setCurrentTheme($theme);
        return $form;
    }
}
