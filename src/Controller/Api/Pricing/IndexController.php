<?php

namespace App\Controller\Api\Pricing;

use App\Service\PricingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class IndexController extends AbstractController
{
    #[Route('/api/plans', name: 'api_pricing_index', methods: ['GET'])]
    public function __invoke(PricingService $pricingService): JsonResponse
    {
        return $this->json(
            $pricingService->getApiIndexPayload()
        );
    }
}