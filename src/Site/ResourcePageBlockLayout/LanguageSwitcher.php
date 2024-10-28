<?php declare(strict_types=1);

namespace Internationalisation\Site\ResourcePageBlockLayout;

use Laminas\View\Renderer\PhpRenderer;
use Omeka\Api\Representation\AbstractResourceEntityRepresentation;
use Omeka\Site\ResourcePageBlockLayout\ResourcePageBlockLayoutInterface;

/**
 * Display the language switcher.
 */
class LanguageSwitcher implements ResourcePageBlockLayoutInterface
{
    public function getLabel() : string
    {
        return 'Language switcher'; // @translate
    }

    public function getCompatibleResourceNames() : array
    {
        return [
            'items',
            'media',
            'item_sets',
        ];
    }

    public function render(PhpRenderer $view, AbstractResourceEntityRepresentation $resource) : string
    {
        return $view->partial('common/resource-page-block-layout/language-switcher', [
            'resource' => $resource,
        ]);
    }
}
