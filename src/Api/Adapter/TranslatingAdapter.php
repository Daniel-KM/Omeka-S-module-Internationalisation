<?php declare(strict_types=1);

namespace Internationalisation\Api\Adapter;

use Common\Stdlib\PsrMessage;
use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Request;
use Omeka\Entity\EntityInterface;
use Omeka\Stdlib\ErrorStore;

class TranslatingAdapter extends AbstractEntityAdapter
{
    protected $sortFields = [
        'lang' => 'lang',
        'string' => 'string',
        'translated' => 'translated',
    ];

    protected $scalarFields = [
        'lang' => 'lang',
        'string' => 'string',
        'translated' => 'translated',
    ];

    public function getResourceName()
    {
        return 'translatings';
    }

    public function getRepresentationClass()
    {
        return \Internationalisation\Api\Representation\TranslatingRepresentation::class;
    }

    public function getEntityClass()
    {
        return \Internationalisation\Entity\Translating::class;
    }

    public function buildQuery(QueryBuilder $qb, array $query): void
    {
        $expr = $qb->expr();

        if (isset($query['lang']) && $query['lang'] !== '') {
            // Should not return anything when invalid.
            if (is_string($query['lang'])) {
                $qb
                    ->andWhere($expr->eq(
                        'omeka_root.lang',
                        $this->adapter->createNamedParameter($qb, $query['lang'])
                    ));
            } else {
                $qb
                    ->andWhere($expr->isNull('omeka_root.lang'));
            }
        }

        if (isset($query['string']) && $query['string'] !== '') {
            // Should not return anything when invalid.
            if (is_string($query['string'])) {
                $qb
                    ->andWhere($expr->eq(
                        'omeka_root.string',
                        $this->adapter->createNamedParameter($qb, $query['string'])
                    ));
            } else {
                $qb
                    ->andWhere($expr->isNull('omeka_root.string'));
            }
        }

        // Normally, the translated string is not used.
        if (isset($query['translated']) && $query['translated'] !== '') {
            // Should not return anything when invalid.
            if (is_string($query['translated'])) {
                $qb
                    ->andWhere($expr->eq(
                        'omeka_root.translated',
                        $this->adapter->createNamedParameter($qb, $query['translated'])
                    ));
            } else {
                $qb
                    ->andWhere($expr->isNull('omeka_root.translated'));
            }
        }
    }

    public function validateRequest(Request $request, ErrorStore $errorStore)
    {
        $language = $request->getValue('o:lang');
        if (!$language || !is_string($language) || strlen($language) > 8) {
            $errorStore->addError('o-module-internationalisation:language', 'The language should be a valid string shorter than 8 characters.'); // @translate
        } elseif (!preg_match('~^[a-zA-Z]{2,3}((-|_)[a-zA-Z0-9]{2,4})?$~', $language)) {
            $errorStore->addError('o-module-internationalisation:language', 'The language should be a valid locale, so a two character code with an optional localization code separated with a "-" or "_".'); // @translate
        }

        $string = $request->getValue('o-module-internationalisation:string');
        if (!$string || !is_string($string) || !strlen($string)) {
            $errorStore->addError('o-module-internationalisation:string', 'The string to translate is not set.'); // @translate
        }

        $translated = $request->getValue('o-module-internationalisation:translated');
        if (!$translated|| !is_string($translated) || !strlen($translated)) {
            $errorStore->addError('o-module-internationalisation:translated', 'The translated string is not set.'); // @translate
        }
    }

    public function hydrate(Request $request, EntityInterface $entity, ErrorStore $errorStore): void
    {
        /** @var \Internationalisation\Entity\Translating $entity */

        $data = $request->getContent();

        if ($this->shouldHydrate($request, 'o:lang')) {
            $language = (string) $data['o:lang'];
            $language = strtolower(strtr($language, '_', '-'));
            $entity->setlang($language);
        }

        if ($this->shouldHydrate($request, 'o-module-internationalisation:string')) {
            $entity->setString((string) $data['o-module-internationalisation:string']);
        }

        if ($this->shouldHydrate($request, 'o-module-internationalisation:translated')) {
            $entity->setTranslated((string) $data['o-module-internationalisation:translated']);
        }
    }

    public function validateEntity(EntityInterface $entity, ErrorStore $errorStore)
    {
        /** @var \Internationalisation\Entity\Translating $entity */

        $language = $entity->getLang();
        $string = $entity->getString();
        if (!$this->isUnique($entity, ['lang' => $language, 'string' => $string])) {
            $errorStore->addError('o-module-internationalisation:string', new PsrMessage(
                'The string "{string}" and language "{language}" to translate must be unique.', // @translate
                ['string' => $string, 'language' => $language]
            ));
        }
    }
}
