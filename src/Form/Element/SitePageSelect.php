<?php
namespace LanguageSwitcher\Form\Element;

class SitePageSelect extends AbstractGroupBySiteSelect
{
    public function getResourceName()
    {
        return 'site_pages';
    }

    public function getValueLabel($resource)
    {
        return $resource->title();
    }
}
