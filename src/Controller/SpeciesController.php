<?php

/**
 * This file is part of the Fauna plugin for the Inachis framework
 *
 * @package Fauna
 * @license https://github.com/inachisphp/plugin-fauna/blob/main/LICENSE.md
 */

namespace Inachis\Fauna\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Inachis\Fauna\Entity\Species;
use Inachis\Fauna\Entity\Taxonomy;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SpeciesController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}

    #[Route('/species/{id}', name: 'fauna_species_show', methods: ['GET'])]
    public function show(string $id): Response
    {
        $species = $this->em->getRepository(Species::class)->find($id);

        if (!$species) {
            throw $this->createNotFoundException('Species not found');
        }

        // Get taxonomy path (genus/family/etc.)
        $taxonomyPath = [];
        if ($species->getGenus()) {
            $taxonomyPath = $this->em->getRepository(Taxonomy::class)->getPath($species->getGenus());
        }

        // Get other species in the same genus
        $related = [];
        if ($species->getGenus()) {
            $related = $this->em->getRepository(Species::class)->findByGenus($species->getGenus());
        }

        return $this->render('@Fauna/species/show.html.twig', [
            'species' => $species,
            'taxonomy_path' => $taxonomyPath,
            'related' => $related,
        ]);
    }

    #[Route('/species', name: 'fauna_species_list', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $repository = $this->em->getRepository(Species::class);

        if ($q === '') {
            return $this->redirectToRoute('fauna_taxonomy_index');
        }

        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $speciesList = $repository->search($q, $limit, $offset);
        $totalCount = $repository->searchCount($q);
        $totalPages = (int) ceil($totalCount / $limit);

        return $this->render('@Fauna/species/list.html.twig', [
            'species_list' => $speciesList,
            'query' => $q,
            'page' => $page,
            'totalPages' => $totalPages,
            'totalCount' => $totalCount,
        ]);
    }

    #[Route('/api/species/search', name: 'fauna_species_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $q = $request->query->get('q', '');
        if ($q === '') {
            return new JsonResponse([]);
        }

        $results = $this->em->getRepository(Species::class)->search($q, 10);
        $data = [];
        foreach ($results as $species) {
            $data[] = [
                'id' => $species->getId()->toString(),
                'name' => $species->getName(),
                'latin' => $species->getLatin(),
            ];
        }

        return new JsonResponse($data);
    }
}
