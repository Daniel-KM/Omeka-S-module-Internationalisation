<?php declare(strict_types=1);
namespace Internationalisation\Api\Adapter;

use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Laminas\EventManager\Event;
use Omeka\Api\Adapter\AbstractEntityAdapter;
use Omeka\Api\Exception;
use Omeka\Api\Request;
use Omeka\Api\Response;
use Omeka\Entity\EntityInterface;
use Omeka\Stdlib\ErrorStore;

class SitePageRelationAdapter extends AbstractEntityAdapter
{
    protected $sortFields = [
        'page_id' => 'page_id',
        'related_page_id' => 'related_page_id',
        // TODO Sort related pages by page slug?
        // 'page_slug' => 'page_slug',
        // 'related_page_slug' => 'related_page_slug',
    ];

    public function getResourceName()
    {
        return 'site_page_relations';
    }

    public function getRepresentationClass()
    {
        return \Internationalisation\Api\Representation\SitePageRelationRepresentation::class;
    }

    public function getEntityClass()
    {
        return \Internationalisation\Entity\SitePageRelation::class;
    }

    public function hydrate(Request $request, EntityInterface $entity, ErrorStore $errorStore): void
    {
        /** @var \Internationalisation\Entity\SitePageRelation $entity */
        $data = $request->getContent();
        if (Request::CREATE === $request->getOperation()) {
            $sitePageAdapter = $this->getAdapter('site_pages');
            $page = $sitePageAdapter->findEntity($data['o:page']['o:id']);
            $relatedPage = $sitePageAdapter->findEntity($data['o-module-internationalisation:related_page']['o:id']);
            // Useless, but cleaner.
            if ($data['o:page']['o:id'] > $data['o-module-internationalisation:related_page']['o:id']) {
                $entity
                    ->setPage($relatedPage)
                    ->setRelatedPage($page);
            } else {
                $entity
                    ->setPage($page)
                    ->setRelatedPage($relatedPage);
            }
        }
        // This entity cannot be updated currently.
    }

    public function validateEntity(EntityInterface $entity, ErrorStore $errorStore): void
    {
        /** @var \Internationalisation\Entity\SitePageRelation $entity */
        $page = $entity->getPage();
        $relatedPage = $entity->getRelatedPage();
        if (!$page) {
            $errorStore->addError('o:page', 'A relation between pages must have a page.'); // @translate
        }
        if (!$relatedPage) {
            $errorStore->addError('o-module-internationalisation:related_page', 'A relation between pages must have a related page.'); // @translate
        } elseif ($page && $page->getId() === $relatedPage->getId()) {
            $errorStore->addError('o:page', 'The page and the related page of a relation between pages must be different.'); // @translate
        }
    }

    public function buildQuery(QueryBuilder $qb, array $query): void
    {
        // TODO Check if the join with the site allows really to check rights/visibility and is really needed.
        $expr = $qb->expr();

        if (isset($query['relation'])) {
            if (!is_array($query['relation'])) {
                $query['relation'] = [$query['relation']];
            }
            // The "relation" may be page id or related page id, because they
            // are pairs (both ways).
            $pageAlias = $this->createAlias();
            $relatedPageAlias = $this->createAlias();
            $qb->innerJoin(
                'omeka_root.page',
                $pageAlias
            );
            $qb->innerJoin(
                'omeka_root.relatedPage',
                $relatedPageAlias
            );
            $qb->where($expr->orX(
                $expr->in(
                    $pageAlias . '.id',
                    $this->createNamedParameter($qb, $query['relation'])
                ),
                $expr->in(
                    $relatedPageAlias . '.id',
                    $this->createNamedParameter($qb, $query['relation'])
                )
            ));
        }

        if (isset($query['page_id'])) {
            if (!is_array($query['page_id'])) {
                $query['page_id'] = [$query['page_id']];
            }
            $pageAlias = $this->createAlias();
            $qb->innerJoin(
                'omeka_root.page',
                $pageAlias
            );
            $qb->andWhere($expr->in(
                $pageAlias . '.id',
                $this->createNamedParameter($qb, $query['page_id'])
            ));
        }

        if (isset($query['related_page_id'])) {
            if (!is_array($query['related_page_id'])) {
                $query['related_page_id'] = [$query['related_page_id']];
            }
            $pageAlias = $this->createAlias();
            $qb->innerJoin(
                'omeka_root.relatedPage',
                $pageAlias
            );
            $qb->andWhere($expr->in(
                $pageAlias . '.id',
                $this->createNamedParameter($qb, $query['related_page_id'])
            ));
        }

        // Note: use of a slug requires the site id/slug too to avoid duplicate.
        if (isset($query['page_slug'])) {
            if (!is_array($query['page_slug'])) {
                $query['page_slug'] = [$query['page_slug']];
            }
            $pageAlias = $this->createAlias();
            $qb->innerJoin(
                'omeka_root.page',
                $pageAlias
            );
            $qb->andWhere($expr->in(
                $pageAlias . '.slug',
                $this->createNamedParameter($qb, $query['page_slug'])
            ));
        }

        if (isset($query['related_page_slug'])) {
            if (!is_array($query['related_page_slug'])) {
                $query['related_page_slug'] = [$query['related_page_slug']];
            }
            $pageAlias = $this->createAlias();
            $qb->innerJoin(
                'omeka_root.relatedPage',
                $pageAlias
            );
            $qb->andWhere($expr->in(
                $pageAlias . '.slug',
                $this->createNamedParameter($qb, $query['related_page_slug'])
            ));
        }

        if (isset($query['site_id'])) {
            $pageAlias = $this->createAlias();
            $qb->innerJoin(
                'omeka_root.page',
                $pageAlias
            );
            $qb->andWhere($expr->in(
                $pageAlias . '.site',
                $this->createNamedParameter($qb, $query['site_id'])
            ));
        }
    }

    /**
     * Site page relation has a composite primary key, not an id, and has no
     * scalar field.
     *
     * {@inheritDoc}
     * @see \Omeka\Api\Adapter\AbstractEntityAdapter::search()
     */
    public function search(Request $request)
    {
        $query = $request->getContent();

        // Set default query parameters
        $defaultQuery = [
            'page' => null,
            'per_page' => null,
            'limit' => null,
            'offset' => null,
            'sort_by' => null,
            'sort_order' => null,
        ];
        $query += $defaultQuery;
        $query['sort_order'] = strtoupper((string) $query['sort_order']) === 'DESC' ? 'DESC' : 'ASC';

        // Begin building the search query.
        $entityClass = $this->getEntityClass();

        $this->index = 0;
        $qb = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('omeka_root')
            ->from($entityClass, 'omeka_root');
        $this->buildQuery($qb, $query);
        // $qb->groupBy('omeka_root.id');

        // Trigger the search.query event.
        $event = new Event('api.search.query', $this, [
            'queryBuilder' => $qb,
            'request' => $request,
        ]);
        $this->getEventManager()->triggerEvent($event);

        // Add the LIMIT clause.
        $this->limitQuery($qb, $query);

        // Before adding the ORDER BY clause, set a paginator responsible for
        // getting the total count. This optimization excludes the ORDER BY
        // clause from the count query, greatly speeding up response time.
        $countQb = clone $qb;
        $countQb->select('1')->resetDQLPart('orderBy');
        $countPaginator = new Paginator($countQb, false);
        // @see https://stackoverflow.com/questions/36199027/not-all-identifier-properties-can-be-found-in-the-resultsetmapping
        // @see https://github.com/doctrine/orm/issues/2596
        $countPaginator->setUseOutputWalkers(false);

        // Add the ORDER BY clause. Always sort by entity ID in addition to any
        // sorting the adapters add.
        $this->sortQuery($qb, $query);
        $qb->addOrderBy('omeka_root.page', $query['sort_order']);

        // TODO Make return scalar working for site page relations.
        $scalarField = $request->getOption('returnScalar');
        if ($scalarField) {
            $fieldNames = $this->getEntityManager()->getClassMetadata($entityClass)->getFieldNames();
            if (!in_array($scalarField, $fieldNames)) {
                throw new Exception\BadRequestException(sprintf(
                    $this->getTranslator()->translate('The "%s" field is not available in the %s entity class.'),
                    $scalarField, $entityClass
                ));
            }
            $qb->select('omeka_root.' . $scalarField);
            $content = array_column($qb->getQuery()->getScalarResult(), $scalarField);
            $response = new Response($content);
            $response->setTotalResults(count($content));
            return $response;
        }

        $paginator = new Paginator($qb, false);
        $entities = [];
        // Don't make the request if the LIMIT is set to zero. Useful if the
        // only information needed is total results.
        if ($qb->getMaxResults() || null === $qb->getMaxResults()) {
            foreach ($paginator as $entity) {
                if (is_array($entity)) {
                    // Remove non-entity columns added to the SELECT. You can use
                    // "AS HIDDEN {alias}" to avoid this condition.
                    $entity = $entity[0];
                }
                $entities[] = $entity;
            }
        }

        $response = new Response($entities);
        $response->setTotalResults($countPaginator->count());
        return $response;
    }
}
