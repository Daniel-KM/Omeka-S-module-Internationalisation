<?php declare(strict_types=1);

namespace Internationalisation\Api\Representation;

use Omeka\Api\Representation\AbstractEntityRepresentation;

class TranslatingRepresentation extends AbstractEntityRepresentation
{
    /**
     * @var \Internationalisation\Entity\Translating
     */
    protected $resource;

    /**
     * @todo The name should not be the same than module Translator, that has no controller for now.
     * @see \Internationalisation\Api\Representation\TranslatingRepresentation
     * @see \Translator\Api\Representation\TranslationRepresentation
     *
     * {@inheritDoc}
     * @see \Omeka\Api\Representation\AbstractResourceRepresentation::getControllerName()
     */
    public function getControllerName()
    {
        return 'translation';
    }

    /**
     * The Json-LD name is Translation or TranslationSimple or TranslationEnglish externally, not
     * Translating, for compatibility with module Translator.
     *
     * {@inheritDoc}
     * @see \Omeka\Api\Representation\AbstractResourceRepresentation::getJsonLdType()
     */
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
            'o-module-internationalisation:translation' => $this->translation(),
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

    public function translation(): string
    {
        return $this->resource->getTranslation();
    }
}
