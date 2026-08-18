<?php

namespace App\Controller\Web\Pricing;

use App\Service\PricingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class IndexController extends AbstractController
{
    #[Route('/plans', name: 'pricing_index', methods: ['GET'])]
    public function __invoke(PricingService $pricingService): Response
    {
        return $this->render(
            'pricing/pricing.html.twig',
            $pricingService->getIndexData()
        );
    }
}