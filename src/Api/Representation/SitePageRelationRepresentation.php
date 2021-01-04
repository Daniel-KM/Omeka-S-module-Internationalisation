<?php declare(strict_types=1);
namespace Internationalisation\Api\Representation;

use Omeka\Api\Representation\AbstractEntityRepresentation;

class SitePageRelationRepresentation extends AbstractEntityRepresentation
{
    public function getControllerName()
    {
        return 'internationalisation';
    }

    public function getJsonLdType()
    {
        return 'o-module-internationalisation:SitePageRelation';
    }

    public function getJsonLd()
    {
        return [
            'o:page' => $this->page()->getReference(),
            'o-module-internationalisation:related_page' => $this->relatedPage()->getReference(),
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
