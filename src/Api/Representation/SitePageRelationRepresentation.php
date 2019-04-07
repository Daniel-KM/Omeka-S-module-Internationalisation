<?php
namespace LanguageSwitcher\Api\Representation;

use Omeka\Api\Representation\AbstractEntityRepresentation;

class SitePageRelationRepresentation extends AbstractEntityRepresentation
{
    public function getControllerName()
    {
        return 'language-switcher';
    }

    public function getJsonLdType()
    {
        return 'o-module-language-switcher:SitePageRelation';
    }

    public function getJsonLd()
    {
        return [
            'o:page' => $this->page()->getReference(),
            'o-module-language-switcher:related_page' => $this->relatedPage()->getReference(),
        ];
    }

    /**
     * @return \Omeka\Api\Representation\SitePageRepresentation
     */
    public function page()
    {
        return $this->getAdapter('site_pages')
            ->getRepresentation($this->resource->getPage());
    }

    /**
     * @return \Omeka\Api\Representation\SitePageRepresentation
     */
    public function relatedPage()
    {
        return $this->getAdapter('site_pages')
            ->getRepresentation($this->resource->getRelatedPage());
    }
}
