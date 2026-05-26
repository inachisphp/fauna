<?php

/**
 * This file is part of the Fauna plugin for the Inachis framework
 *
 * @package Fauna
 * @license https://github.com/inachisphp/plugin-fauna/blob/main/LICENSE.md
 */

namespace Inachis\Fauna\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Inachis\Fauna\Entity\FeaturedSpecies;
use Inachis\Fauna\Form\FeaturedSpeciesType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class FeaturedController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}

    #[Route('/manage/featured', name: 'fauna_featured_manage', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function manage(): Response
    {
        $allFeatured = $this->em->getRepository(FeaturedSpecies::class)->findBy(
            [],
            ['dateAdded' => 'DESC']
        );

        return $this->render('@Fauna/featured/manage.html.twig', [
            'all_featured' => $allFeatured,
        ]);
    }

    #[Route('/manage/featured/new', name: 'fauna_featured_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function new(Request $request): Response
    {
        $featured = new FeaturedSpecies();
        $form = $this->createForm(FeaturedSpeciesType::class, $featured);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($featured);
            $this->em->flush();

            $this->addFlash('success', 'Featured species scheduled successfully.');

            return $this->redirectToRoute('fauna_featured_manage');
        }

        return $this->render('@Fauna/featured/edit.html.twig', [
            'form' => $form->createView(),
            'featured' => $featured,
            'is_new' => true,
        ]);
    }

    #[Route('/manage/featured/edit/{id}', name: 'fauna_featured_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(string $id, Request $request): Response
    {
        $featured = $this->em->getRepository(FeaturedSpecies::class)->find($id);

        if (!$featured) {
            throw $this->createNotFoundException('Featured species entry not found');
        }

        $form = $this->createForm(FeaturedSpeciesType::class, $featured);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();

            $this->addFlash('success', 'Featured species updated successfully.');

            return $this->redirectToRoute('fauna_featured_manage');
        }

        return $this->render('@Fauna/featured/edit.html.twig', [
            'form' => $form->createView(),
            'featured' => $featured,
            'is_new' => false,
        ]);
    }
}
