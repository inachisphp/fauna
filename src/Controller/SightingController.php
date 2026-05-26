<?php

/**
 * This file is part of the Fauna plugin for the Inachis framework
 *
 * @package Fauna
 * @license https://github.com/inachisphp/plugin-fauna/blob/main/LICENSE.md
 */

namespace Inachis\Fauna\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Inachis\Fauna\Entity\Sighting;
use Inachis\Fauna\Form\SightingType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class SightingController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}

    #[Route('/lifelist', name: 'fauna_lifelist', methods: ['GET'])]
    public function lifelist(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login'); // fallback standard login
        }

        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $sightingRepo = $this->em->getRepository(Sighting::class);
        // User entity is passed to get Lifelist
        $speciesList = $sightingRepo->getLifelist($user, $limit, $offset);
        $totalCount = $sightingRepo->getLifelistCount($user);
        $totalPages = (int) ceil($totalCount / $limit);

        return $this->render('@Fauna/sighting/lifelist.html.twig', [
            'results' => $speciesList,
            'resultCount' => $totalCount,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    #[Route('/sightings/new', name: 'fauna_sighting_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(Request $request): Response
    {
        $user = $this->getUser();
        $sighting = new Sighting(null, $user);

        // Prepopulate trip if provided in request
        $tripId = $request->query->get('trip');
        if ($tripId) {
            $trip = $this->em->getRepository(\Inachis\Fauna\Entity\Trip::class)->find($tripId);
            if ($trip && $trip->getUser() === $user) {
                $sighting->setTrip($trip);
            }
        }

        $form = $this->createForm(SightingType::class, $sighting);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($sighting);
            $this->em->flush();

            $this->addFlash('success', 'Sighting recorded successfully.');

            if ($sighting->getTrip()) {
                return $this->redirectToRoute('fauna_trip_show', ['id' => $sighting->getTrip()->getId()->toString()]);
            }

            return $this->redirectToRoute('fauna_lifelist');
        }

        return $this->render('@Fauna/sighting/edit.html.twig', [
            'form' => $form->createView(),
            'sighting' => $sighting,
            'is_new' => true,
        ]);
    }

    #[Route('/sightings/{id}', name: 'fauna_sighting_show', methods: ['GET'])]
    public function show(string $id): Response
    {
        $sighting = $this->em->getRepository(Sighting::class)->find($id);

        if (!$sighting) {
            throw $this->createNotFoundException('Sighting not found');
        }

        return $this->render('@Fauna/sighting/show.html.twig', [
            'sighting' => $sighting,
        ]);
    }

    #[Route('/sightings/edit/{id}', name: 'fauna_sighting_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function edit(string $id, Request $request): Response
    {
        $sighting = $this->em->getRepository(Sighting::class)->find($id);

        if (!$sighting) {
            throw $this->createNotFoundException('Sighting not found');
        }

        if ($sighting->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('You do not own this sighting');
        }

        $form = $this->createForm(SightingType::class, $sighting);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();

            $this->addFlash('success', 'Sighting updated successfully.');

            return $this->redirectToRoute('fauna_sighting_show', ['id' => $sighting->getId()->toString()]);
        }

        return $this->render('@Fauna/sighting/edit.html.twig', [
            'form' => $form->createView(),
            'sighting' => $sighting,
            'is_new' => false,
        ]);
    }

    #[Route('/sightings/delete/{id}', name: 'fauna_sighting_delete', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function delete(string $id, Request $request): Response
    {
        $sighting = $this->em->getRepository(Sighting::class)->find($id);

        if (!$sighting) {
            throw $this->createNotFoundException('Sighting not found');
        }

        if ($sighting->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('You do not own this sighting');
        }

        if ($this->isCsrfTokenValid('delete-sighting-' . $id, $request->request->get('_token'))) {
            $this->em->remove($sighting);
            $this->em->flush();
            $this->addFlash('success', 'Sighting deleted successfully.');
        }

        return $this->redirectToRoute('fauna_lifelist');
    }
}
