<?php

namespace App\Controller\Web\Rating;

use App\Entity\User;
use App\Repository\ReviewRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/ratings', name: 'app_ratings_')]
class MyRatingsController extends AbstractController
{
    #[Route('/received', name: 'received', methods: ['GET'])]
    public function received(ReviewRepository $reviewRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $me = $this->getUser();
        if (!$me instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $reviews = $reviewRepository->findAllForProfile($me);
        
        return $this->render('rating/received.html.twig', [
            'reviews' => $reviews,
        ]);
    }
}