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
use Inachis\Fauna\Entity\Sighting;
use Inachis\Fauna\Entity\Species;
use Inachis\Fauna\Entity\Trip;
use Inachis\Entity\User;

/**
 * @extends ServiceEntityRepository<Sighting>
 */
class SightingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Sighting::class);
    }

    /**
     * Get unique species sighted by the user (Lifelist)
     * @return array<Species>
     */
    public function getLifelist(User $user, int $limit = 20, int $offset = 0): array
    {
        $results = $this->createQueryBuilder('s')
            ->select('DISTINCT sp')
            ->join('s.species', 'sp')
            ->where('s.user = :user')
            ->setParameter('user', $user)
            ->orderBy('sp.name', 'ASC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $results;
    }

    /**
     * Get count of unique species sighted by the user
     */
    public function getLifelistCount(User $user): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(DISTINCT s.species)')
            ->where('s.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get sightings for a specific trip
     * @return array<Sighting>
     */
    public function findByTrip(Trip $trip): array
    {
        return $this->findBy(
            ['trip' => $trip],
            ['date' => 'DESC']
        );
    }

    /**
     * Get total sum/count of animals sighted on a trip
     */
    public function getSightingCountForTrip(Trip $trip): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('SUM(s.count)')
            ->where('s.trip = :trip')
            ->setParameter('trip', $trip)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;
    }

    /**
     * Get total count of sightings for a trip
     */
    public function countByTrip(Trip $trip): int
    {
        return $this->count(['trip' => $trip]);
    }
}
