<?php

namespace App\Controller\Web\Annonce;

use App\Entity\Annonce;
use App\Entity\User;
use App\Repository\RatingRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AnnoncePublicController extends AbstractController
{
    #[Route('/annonces/{slug}', name: 'annonce_show', methods: ['GET'], requirements: ['slug' => '[a-f0-9]{64}'])]
    public function __invoke(
        #[MapEntity(mapping: ['slug' => 'slug'])]
        Annonce $annonce,
        RatingRepository $ratingRepository,
    ): Response
    {
        $viewer = $this->getUser();
        $isOwner = $viewer instanceof User && $viewer->getId() === $annonce->getUser()?->getId();
        $isStaff = $this->isGranted('ROLE_MODERATEUR')
            || $this->isGranted('ROLE_ADMIN')
            || $this->isGranted('ROLE_SUPER_ADMIN');

        if ((!$annonce->isAfficherMaintenant() || !$annonce->isPublier()) && !$isOwner && !$isStaff) {
            throw $this->createNotFoundException('Cette annonce n’est pas publiée.');
        }

        return $this->render('annonce/show.html.twig', [
            'annonce' => $annonce,
            'isOwner' => $isOwner,
            'isStaff' => $isStaff,
            'ratingStats' => $annonce->getUser()
                ? $ratingRepository->getStatsForTalent((int) $annonce->getUser()->getId())
                : ['avg' => 0, 'voters' => 0],
        ]);
    }
}
