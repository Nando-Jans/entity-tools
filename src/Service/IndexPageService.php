<?php

namespace NandoJans\EntityTools\Service;

use Doctrine\ORM\EntityManagerInterface;
use NandoJans\EntityTools\Config\EntityConfig;
use NandoJans\EntityTools\Contract\ListableRepositoryInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class IndexPageService
{
    public function __construct(private Security $security, private EntityManagerInterface $em, private UrlGeneratorInterface $router, private EntityConfigRegistry $configs)
    {
    }

    public function getResult(string $entityClass, array $filters = [], ?int $start = null, ?int $limit = null, ?string $sortField = null, string $sortDirection = 'ASC', ?callable $configure = null): array
    {
        $repository = $this->em->getRepository($entityClass);
        if (!$repository instanceof ListableRepositoryInterface) throw new \LogicException("$entityClass must have a listable repository.");
        $qb = $repository->createListQueryBuilder($this->security->getUser());
        $columns = $this->configs->get($entityClass)->getIndexTableColumns();
        if ($configure) $qb = $configure($qb) ?? $qb;
        foreach ($filters as $field => $value) {
            $filter = $columns[$field]['filter'] ?? null;
            if (is_string($filter)) $qb->andWhere("$filter LIKE :$field")->setParameter($field, "%$value%");
            elseif (is_callable($filter)) $qb = $filter($qb, $value) ?? $qb;
        }
        if ($sortField && isset($columns[$sortField])) {
            $sort = $columns[$sortField]['sort'] ?? $columns[$sortField]['filter'] ?? null;
            $sortDirection = strtoupper($sortDirection) === 'DESC' ? 'DESC' : 'ASC';
            if (is_string($sort)) $qb->orderBy($sort, $sortDirection); elseif (is_callable($sort)) $qb = $sort($qb, $sortDirection) ?? $qb;
        }
        $sortValue = $sortField ? ($columns[$sortField]['sortValue'] ?? null) : null;
        if (is_callable($sortValue)) {
            $entities = $qb->getQuery()->getResult();
            $direction = strtoupper($sortDirection) === 'DESC' ? -1 : 1;
            usort($entities, static fn ($left, $right) => $direction * ($sortValue($left) <=> $sortValue($right)));
            return array_slice($entities, $start ?? 0, $limit);
        }

        if ($start !== null) $qb->setFirstResult($start);
        if ($limit !== null) $qb->setMaxResults($limit);
        return $qb->getQuery()->getResult();
    }

    public function getIndexTable(Request $request, string $entityClass, ?callable $configure = null): IndexTable
    {
        $filters = $this->filters($request);
        $sortField = $request->query->getString('sortField') ?: null;
        $sortDirection = strtoupper($request->query->getString('sortDirection', 'ASC')) === 'DESC' ? 'DESC' : 'ASC';
        $total = count($this->getResult($entityClass, $filters, configure: $configure));
        $count = max($request->query->getInt('itemCount', 20), 1);
        $pages = max((int)ceil($total / $count), 1);
        $page = max(min($request->query->getInt('page', 1), $pages), 1);
        $from = ($page - 1) * $count;
        $to = min($page * $count, $total);
        $config = $this->configs->get($entityClass);
        $columns = $this->filterColumns($config->getIndexTableColumns(), $request->query->all('columns') ?: $request->query->get('columns'));
        $user = $this->security->getUser();
        if (!$user instanceof UserInterface) throw new \LogicException('A logged-in user is required.');
        return new IndexTable(EntityConfig::getEntityName($entityClass), $this->getResult($entityClass, $filters, $from, $count, $sortField, $sortDirection, $configure), $columns, $total, $from, $to, $page, $pages, $sortField, $sortDirection, $config, $this->router, $user);
    }

    private function filters(Request $request): array
    {
        $filters = [];
        for ($i = 1; $request->query->has("filter{$i}Field"); ++$i) {
            $field = $request->query->getString("filter{$i}Field");
            $value = $request->query->getString("filter{$i}Value");
            if ($field !== '' && $value !== '') $filters[$field] = $value;
        }
        return $filters;
    }

    private function filterColumns(array $columns, array|string|null $requested): array
    {
        if (!$requested) return $columns;
        if (is_string($requested)) $requested = array_filter(array_map('trim', explode(',', $requested)));
        return array_intersect_key($columns, array_flip($requested)) ?: $columns;
    }
}
