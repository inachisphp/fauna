<?php

/**
 * This file is part of the Fauna plugin for the Inachis framework
 *
 * @package Fauna
 * @license https://github.com/inachisphp/plugin-fauna/blob/main/LICENSE.md
 */

namespace Inachis\Fauna\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Inachis\Fauna\Entity\Taxonomy;
use Inachis\Fauna\Entity\Species;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TaxonomyController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}

    #[Route('/tax', name: 'fauna_taxonomy_index', methods: ['GET'])]
    public function index(): Response
    {
        $roots = $this->em->getRepository(Taxonomy::class)->findBy(['parent' => null], ['name' => 'ASC']);

        return $this->render('@Fauna/taxonomy/index.html.twig', [
            'roots' => $roots,
        ]);
    }

    #[Route('/tax/{id}', name: 'fauna_taxonomy_show', methods: ['GET'])]
    public function show(string $id): Response
    {
        $taxonomy = $this->em->getRepository(Taxonomy::class)->find($id);

        if (!$taxonomy) {
            throw $this->createNotFoundException('Taxonomy not found');
        }

        // Get sub-groups (children)
        $children = $taxonomy->getChildren()->toArray();

        usort($children, function ($a, $b) {
            $typeComparison = $a->getTypeEnum()?->sortOrder()
                <=> $b->getTypeEnum()?->sortOrder();

            if ($typeComparison !== 0) {
                return $typeComparison;
            }

            return strcmp($a->getName(), $b->getName());
        });

        // Get species directly in this taxonomic node (e.g., if it is a Genus)
        $speciesRepo = $this->em->getRepository(Species::class);
        $species = $speciesRepo->findBy(['genus' => $taxonomy], ['name' => 'ASC']);

        // Resolve parent hierarchy if available
        $parentPath = [];
        if ($taxonomy->getParent()) {
            $parentPath = $this->em->getRepository(Taxonomy::class)->getPath($taxonomy->getParent());
        }

        return $this->render('@Fauna/taxonomy/show.html.twig', [
            'taxonomy' => $taxonomy,
            'children' => $children,
            'species' => $species,
            'parent_path' => $parentPath,
        ]);
    }
}
