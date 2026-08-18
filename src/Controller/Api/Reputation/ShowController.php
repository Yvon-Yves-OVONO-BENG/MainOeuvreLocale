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

final class ShowController extends AbstractController
{
    #[Route('/api/reputation/user/{slug}', name: 'api_reputation_show', methods: ['GET'], requirements: ['slug' => '[A-Za-z0-9._-]+'])]
    public function __invoke(string $slug, Request $request, ReputationService $reputationService): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User $me */
        $me = $this->getUser();

        $editMode = $request->query->getBoolean('edit', false);

        try {
            return $this->json(
                $reputationService->getApiShowPayload($slug, $me, $editMode)
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