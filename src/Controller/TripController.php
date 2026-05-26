<?php

/**
 * This file is part of the Fauna plugin for the Inachis framework
 *
 * @package Fauna
 * @license https://github.com/inachisphp/plugin-fauna/blob/main/LICENSE.md
 */

namespace Inachis\Fauna\Controller;

use Doctrine\ORM\EntityManagerInterface;
use Inachis\Fauna\Entity\Trip;
use Inachis\Fauna\Entity\Sighting;
use Inachis\Fauna\Form\TripType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class TripController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}

    #[Route('/trips', name: 'fauna_trip_list', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function list(Request $request): Response
    {
        $user = $this->getUser();
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $tripRepo = $this->em->getRepository(Trip::class);
        $trips = $tripRepo->findByUser($user, $limit, $offset);
        $totalCount = $tripRepo->countByUser($user);
        $totalPages = (int) ceil($totalCount / $limit);

        // Fetch counts for each trip
        $sightingRepo = $this->em->getRepository(Sighting::class);
        $tripData = [];
        foreach ($trips as $trip) {
            $sightingCount = $sightingRepo->countByTrip($trip);
            
            // Calculate trip length in days
            $days = 0;
            if ($trip->getStartDate() && $trip->getEndDate()) {
                $diff = $trip->getStartDate()->diff($trip->getEndDate());
                $days = $diff->days + 1;
            }

            $tripData[] = [
                'entity' => $trip,
                'sighting_count' => $sightingCount,
                'trip_length' => $days,
            ];
        }

        return $this->render('@Fauna/trip/list.html.twig', [
            'trips' => $tripData,
            'page' => $page,
            'totalPages' => $totalPages,
        ]);
    }

    #[Route('/trips/new', name: 'fauna_trip_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function new(Request $request): Response
    {
        $trip = new Trip('', $this->getUser());
        $form = $this->createForm(TripType::class, $trip);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->persist($trip);
            $this->em->flush();

            $this->addFlash('success', 'Trip created successfully.');

            return $this->redirectToRoute('fauna_trip_list');
        }

        return $this->render('@Fauna/trip/edit.html.twig', [
            'form' => $form->createView(),
            'trip' => $trip,
            'is_new' => true,
        ]);
    }

    #[Route('/trips/{id}', name: 'fauna_trip_show', methods: ['GET'])]
    public function show(string $id): Response
    {
        $trip = $this->em->getRepository(Trip::class)->find($id);

        if (!$trip) {
            throw $this->createNotFoundException('Trip not found');
        }

        $sightings = $this->em->getRepository(Sighting::class)->findByTrip($trip);

        return $this->render('@Fauna/trip/show.html.twig', [
            'trip' => $trip,
            'sightings' => $sightings,
        ]);
    }

    #[Route('/trips/edit/{id}', name: 'fauna_trip_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_USER')]
    public function edit(string $id, Request $request): Response
    {
        $trip = $this->em->getRepository(Trip::class)->find($id);

        if (!$trip) {
            throw $this->createNotFoundException('Trip not found');
        }

        if ($trip->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('You do not own this trip');
        }

        $form = $this->createForm(TripType::class, $trip);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->em->flush();

            $this->addFlash('success', 'Trip updated successfully.');

            return $this->redirectToRoute('fauna_trip_show', ['id' => $trip->getId()->toString()]);
        }

        return $this->render('@Fauna/trip/edit.html.twig', [
            'form' => $form->createView(),
            'trip' => $trip,
            'is_new' => false,
        ]);
    }

    #[Route('/trips/delete/{id}', name: 'fauna_trip_delete', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function delete(string $id, Request $request): Response
    {
        $trip = $this->em->getRepository(Trip::class)->find($id);

        if (!$trip) {
            throw $this->createNotFoundException('Trip not found');
        }

        if ($trip->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('You do not own this trip');
        }

        if ($this->isCsrfTokenValid('delete-trip-' . $id, $request->request->get('_token'))) {
            $this->em->remove($trip);
            $this->em->flush();
            $this->addFlash('success', 'Trip deleted successfully.');
        }

        return $this->redirectToRoute('fauna_trip_list');
    }
}
