<?php

namespace App\Controller\Web\Legal;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PrivacyController extends AbstractController
{
    #[Route('/privacy', name: 'legal_privacy')]
    public function index(): Response
    {
        return $this->render('legal/privacy.html.twig', [
            'lastUpdated' => new \DateTimeImmutable('2026-02-01'),
            'companyName' => "Main d'Oeuvre Locale",
            'supportEmail' => 'support@maindoeuvrelocale.com',
        ]);
    }
}
