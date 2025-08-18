<?php declare(strict_types=1);

namespace Internationalisation\Api\Representation;

use Omeka\Api\Representation\AbstractEntityRepresentation;

class TranslationRepresentation extends AbstractEntityRepresentation
{
    /**
     * @var \Internationalisation\Entity\Translation
     */
    protected $resource;

    public function getControllerName()
    {
        return 'translation';
    }

    public function getJsonLdType()
    {
        return 'o-module-internationalisation:Translation';
    }

    public function getJsonLd()
    {
        return [
            'o:id' => $this->id(),
            'o:lang' => $this->lang(),
            'o-module-internationalisation:string' => $this->string(),
            'o-module-internationalisation:translated' => $this->translated(),
        ];
    }

    public function lang(): string
    {
        return $this->resource->getLang();
    }

    public function string(): string
    {
        return $this->resource->getString();
    }

    public function translated(): string
    {
        return $this->resource->getTranslated();
    }
}
