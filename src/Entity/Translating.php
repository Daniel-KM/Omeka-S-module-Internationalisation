<?php declare(strict_types=1);

namespace Internationalisation\Entity;

use Omeka\Entity\AbstractEntity;

/**
 * Translations have no owner, like resource values. They should be simple and fast.
 * The source language is always English in Omeka, so it is not stored.
 *
 * @todo Find a way to index the first subtag, so the two or three letters language code (rare, but used in watau.fr).
 * Anyway, there are a few number of translations in a site interface.
 *
 * The index should be a unique one, but the size is limited to 190 characters,
 * so there may be a possible issue (not in real cases if used only for interface).
 *
 * @Entity
 * @Table(
 *      indexes={
 *         @Index(
 *             name="idx_translating_lang_string",
 *             columns={
 *                 "lang",
 *                 "string"
 *             },
 *             options={
 *                 "lengths": {null, 190}
 *             }
 *         )
 *     }
 * )
 */
class Translating extends AbstractEntity
{
    /**
     * @var int
     *
     * @Id
     * @Column(
     *     type="integer"
     * )
     * @GeneratedValue
     */
    protected $id;

    /**
     * Language tag according to BCP47, common first two subtags used for sites.
     * @see https://en.wikipedia.org/wiki/IETF_language_tag
     *
     * @var string
     *
     * @Column(
     *     length=8,
     *     nullable=false
     * )
     */
    protected $lang;

    /**
     * @var string
     *
     * @Column(
     *     name="`string`",
     *     type="text",
     *     nullable=false
     * )
     */
    protected $string;

    /**
     * @var string
     *
     * @Column(
     *     type="text",
     *     nullable=false
     * )
     */
    protected $translation;

    public function getId()
    {
        return $this->id;
    }

    public function setLang(string $lang): self
    {
        $this->lang = $lang;
        return $this;
    }

    public function getLang(): string
    {
        return $this->lang;
    }

    public function setString(string $string): self
    {
        $this->string = $string;
        return $this;
    }

    public function getString(): string
    {
        return $this->string;
    }

    public function setTranslation(string $translation): self
    {
        $this->translation = $translation;
        return $this;
    }

    public function getTranslation(): string
    {
        return $this->translation;
    }
}
