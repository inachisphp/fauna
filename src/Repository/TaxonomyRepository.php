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
use Inachis\Fauna\Entity\Taxonomy;

/**
 * @extends ServiceEntityRepository<Taxonomy>
 */
class TaxonomyRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Taxonomy::class);
    }

    /**
     * Find taxonomy entries by type
     *
     * @param string $type
     * @return array
     */
    public function findByType(string $type): array
    {
        return $this->findBy(['type' => $type]);
    }

    /**
     * Find taxonomy entry by external ID
     *
     * @param string $externalId
     * @return Taxonomy|null
     */
    public function findByExternalId(string $externalId): ?Taxonomy
    {
        return $this->findOneBy(['externalId' => $externalId]);
    }
}

