<?php

namespace App\Controller\Api\Reputation;

use App\Entity\User;
use App\Service\ReputationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class RateController extends AbstractController
{
    #[Route('/api/reputation/user/{slug}/rate', name: 'api_reputation_rate', methods: ['POST'], requirements: ['slug' => '[A-Za-z0-9._-]+'])]
    public function __invoke(string $slug, Request $request, ReputationService $reputationService): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $me */
        $me = $this->getUser();

        $payload = json_decode($request->getContent(), true);
        $payload = is_array($payload) ? $payload : [];

        $stars = $payload['stars'] ?? $request->request->get('stars');
        $comment = $payload['comment'] ?? $request->request->get('comment', '');

        if ($stars === null || $stars === '') {
            return $this->json([
                'ok' => false,
                'message' => "Le champ 'stars' est requis.",
            ], 400);
        }

        try {
            return $this->json(
                $reputationService->submitOpinion(
                    $slug,
                    $me,
                    (int) $stars,
                    trim((string) $comment)
                )
            );
        } catch (BadRequestHttpException $exception) {
            return $this->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 400);
        } catch (NotFoundHttpException $exception) {
            return $this->json([
                'ok' => false,
                'message' => $exception->getMessage(),
            ], 404);
        }
    }
}