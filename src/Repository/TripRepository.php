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
use Inachis\Fauna\Entity\Trip;
use Inachis\Entity\User;

/**
 * @extends ServiceEntityRepository<Trip>
 */
class TripRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Trip::class);
    }

    /**
     * @return array<Trip>
     */
    public function findByUser(User $user, int $limit = 20, int $offset = 0): array
    {
        return $this->findBy(
            ['user' => $user],
            ['endDate' => 'DESC', 'startDate' => 'DESC'],
            $limit,
            $offset
        );
    }

    public function countByUser(User $user): int
    {
        return $this->count(['user' => $user]);
    }
}
