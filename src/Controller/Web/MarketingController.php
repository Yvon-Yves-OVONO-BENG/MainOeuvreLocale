<?php

namespace App\Controller\Web;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/apropos', name: 'marketing_')]
final class MarketingController extends AbstractController
{
    #[Route('/notre-histoire', name: 'story', methods: ['GET'])]
    public function story(): Response
    {
        return $this->render('marketing/story.html.twig', [
            'page' => 'story',
        ]);
    }

    #[Route('/notre-vision', name: 'vision', methods: ['GET'])]
    public function vision(): Response
    {
        return $this->render('marketing/vision.html.twig', [
            'page' => 'vision',
        ]);
    }

    #[Route('/notre-mission', name: 'mission', methods: ['GET'])]
    public function mission(): Response
    {
        return $this->render('marketing/mission.html.twig', [
            'page' => 'mission',
        ]);
    }

    #[Route('/nos-services', name: 'services', methods: ['GET'])]
    public function services(): Response
    {
        return $this->render('marketing/services.html.twig', [
            'page' => 'services',
        ]);
    }
}
