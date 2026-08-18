<?php

namespace App\Controller\Web\Review;

use App\Entity\User;
use App\Repository\ReviewRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MyReviewsController extends AbstractController
{
    #[Route('/reviews/me', name: 'review_my_list', methods: ['GET'])]
    public function __invoke(ReviewRepository $reviews): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $me = $this->getUser();
        if (!$me instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $received = $reviews->findReceivedByUser($me, 100);
        $given    = $reviews->findGivenByUser($me, 100);

        return $this->render('review/my_list.html.twig', [
            'received' => $received,
            'given' => $given,
            'countReceived' => $reviews->countReceived($me),
            'countGiven' => $reviews->countGiven($me),
        ]);
    }
}