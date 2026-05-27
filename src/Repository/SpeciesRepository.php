<?php

/**
 * This file is part of the Fauna plugin for the Inachis framework
 *
 * @package Fauna
 * @license https://github.com/inachisphp/plugin-fauna/blob/main/LICENSE.md
 */

namespace Inachis\Fauna\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Inachis\Fauna\Entity\Species;
use Inachis\Fauna\Entity\Taxonomy;

/**
 * @extends ServiceEntityRepository<Species>
 */
class SpeciesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Species::class);
    }

    /**
     * @return array<Species>
     */
    public function search(string $keyword, int $limit = 20, int $offset = 0): array
    {
        return $this->createQueryBuilder('s')
            ->addSelect('(CASE WHEN s.name LIKE :keyword THEN 1 ELSE 0 END) AS HIDDEN name_priority')
            ->where('s.name LIKE :keyword')
            ->orWhere('s.latin LIKE :keyword')
            ->setParameter('keyword', '%' . $keyword . '%')
            ->orderBy('name_priority', 'DESC')
            ->addOrderBy('s.name', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function searchCount(string $keyword): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.name LIKE :keyword')
            ->orWhere('s.latin LIKE :keyword')
            ->setParameter('keyword', '%' . $keyword . '%')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<Species>
     */
    public function findByGenus(Taxonomy $genus, int $limit = 20): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.genus = :genus')
            ->setParameter('genus', $genus)
            ->orderBy('s.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
