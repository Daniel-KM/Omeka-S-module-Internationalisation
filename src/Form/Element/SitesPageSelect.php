<?php declare(strict_types=1);

namespace Internationalisation\Form\Element;

/**
 * Used in:
 *
 * @see \BlockPlus\Form\Element\SitesPageSelect
 * @see \Internationalisation\Form\Element\SitesPageSelect
 */
class SitesPageSelect extends AbstractGroupBySiteSelect
{
    public function getResourceName(): string
    {
        return 'site_pages';
    }

    public function getValueLabel($resource): string
    {
        return (string) $resource->title();
    }
}
