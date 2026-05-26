<?php

/**
 * This file is part of the Fauna plugin for the Inachis framework
 *
 * @package Fauna
 * @license https://github.com/inachisphp/plugin-fauna/blob/main/LICENSE.md
 */

namespace Inachis\Fauna\Repository;

use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Inachis\Fauna\Entity\FeaturedSpecies;

/**
 * @extends ServiceEntityRepository<FeaturedSpecies>
 */
class FeaturedSpeciesRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FeaturedSpecies::class);
    }

    public function findCurrentFeatured(): ?FeaturedSpecies
    {
        $now = new DateTimeImmutable();
        return $this->createQueryBuilder('f')
            ->where('f.isLive = :isLive')
            ->andWhere('f.scheduleDate IS NULL OR f.scheduleDate <= :now')
            ->setParameter('isLive', true)
            ->setParameter('now', $now)
            ->orderBy('f.scheduleDate', 'DESC')
            ->addOrderBy('f.dateAdded', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
