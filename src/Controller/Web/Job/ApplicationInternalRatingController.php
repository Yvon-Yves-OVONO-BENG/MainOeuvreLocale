<?php

namespace App\Controller\Web\Job;

use App\Entity\Application;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class ApplicationInternalRatingController extends AbstractController
{
    #[Route('/publisher/application/{id}/internal-rating', name: 'talent_application_internal_rating', methods: ['POST'])]
    public function __invoke(Application $application, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $me = $this->getUser();
        if (!$me instanceof User || $application->getJob()?->getCreatedBy()?->getId() !== $me->getId()) {
            return $this->json(['ok' => false, 'message' => 'Accès refusé.'], 403);
        }

        $payload = json_decode((string) $request->getContent(), true) ?: [];
        if (!$this->isCsrfTokenValid('application_rating_'.$application->getId(), (string) ($payload['_token'] ?? ''))) {
            return $this->json(['ok' => false, 'message' => 'Jeton de sécurité invalide.'], 403);
        }

        $score = (int) ($payload['score'] ?? 0);
        $note = trim((string) ($payload['note'] ?? ''));
        if ($score < 1 || $score > 10 || mb_strlen($note) > 1500) {
            return $this->json(['ok' => false, 'message' => 'Note ou commentaire invalide.'], 422);
        }

        $application->setInternalScore($score)
            ->setInternalNote($note !== '' ? $note : null)
            ->setInternallyRatedAt(new \DateTimeImmutable());
        $em->flush();

        return $this->json(['ok' => true, 'score' => $score, 'message' => 'Évaluation privée enregistrée.']);
    }
}
