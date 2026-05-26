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
    public function search(string $keyword, int $limit = 20): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.name LIKE :keyword')
            ->orWhere('s.latin LIKE :keyword')
            ->setParameter('keyword', '%' . $keyword . '%')
            ->orderBy('s.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
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
