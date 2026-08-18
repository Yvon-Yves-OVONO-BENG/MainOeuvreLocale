<?php

namespace App\Controller\Web\Legal;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TermsController extends AbstractController
{
    #[Route('/terms', name: 'legal_terms')]
    public function index(): Response
    {
        return $this->render('legal/terms.html.twig', [
            'lastUpdated' => new \DateTimeImmutable('2026-02-01'),
            'companyName' => "Main d'Oeuvre Locale",
            'supportEmail' => 'support@maindoeuvrelocale.com',
        ]);
    }
}
