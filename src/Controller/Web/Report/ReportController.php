<?php
// src/Controller/ReportController.php

namespace App\Controller\Web\Report;

use App\Entity\Report;
use App\Entity\User;
use App\Repository\ReportRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[Route('/report', name: 'report_')]
class ReportController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ReportRepository $reportRepo,
    ) {}

    private function requireUser(): User
    {
        $me = $this->getUser();
        if (!$me instanceof User) {
            throw new AccessDeniedException();
        }
        return $me;
    }

    /**
     * ✅ Etat du signalement (pour afficher bouton: Signaler / Annuler)
     * URL utilisée par: data-state-url
     */
    #[Route('/user/{id}/state', name: 'user_state', methods: ['GET'])]
    public function state(int $id): JsonResponse
    {
        $me = $this->requireUser();

        // on ne se signale pas soi-même
        if ($me->getId() === $id) {
            return $this->json(['ok' => true, 'reported' => false, 'reportId' => null]);
        }

        $open = $this->reportRepo->findOpenUserReport($me, $id);

        return $this->json([
            'ok' => true,
            'reported' => $open !== null,
            'reportId' => $open?->getId(),
        ]);
    }

    /**
     * ✅ Créer un signalement sur un utilisateur
     * URL utilisée par: data-create-url
     * Payload JSON: { "reason": "..." }
     */
    #[Route('/user/{id}', name: 'user_crea', methods: ['POST'])]
    public function create(int $id, Request $request): JsonResponse
    {
        $me = $this->requireUser();

        // on ne se signale pas soi-même
        if ($me->getId() === $id) {
            return $this->json(['ok' => false, 'error' => 'self_report_forbidden'], 400);
        }

        /** @var User|null $target */
        $target = $this->em->getRepository(User::class)->find($id);
        if (!$target) {
            return $this->json(['ok' => false, 'error' => 'target_not_found'], 404);
        }

        // ✅ Empêche doublon: un report OPEN max
        $existing = $this->reportRepo->findOpenUserReport($me, $id);
        if ($existing) {
            return $this->json([
                'ok' => true,
                'already' => true,
                'reportId' => $existing->getId(),
            ]);
        }

        $payload = json_decode((string) $request->getContent(), true) ?: [];
        $reason = trim((string)($payload['reason'] ?? ''));

        if (mb_strlen($reason) < 10) {
            return $this->json(['ok' => false, 'error' => 'reason_too_short'], 422);
        }

        $report = (new Report())
            ->setReporter($me)
            ->setTargetUser($target)
            ->setReason($reason)
            ->setStatus(Report::STATUS_OPEN);

        $this->em->persist($report);
        $this->em->flush();

        return $this->json([
            'ok' => true,
            'reportId' => $report->getId(),
        ]);
    }

    /**
     * ✅ Annuler un signalement (seulement par le reporter)
     * URL utilisée par: data-cancel-url (tpl avec id=0)
     */
    #[Route('/{id}/cancel', name: 'cancel', methods: ['POST'])]
    public function cancel(int $id): JsonResponse
    {
        $me = $this->requireUser();

        /** @var Report|null $report */
        $report = $this->reportRepo->find($id);
        if (!$report) {
            return $this->json(['ok' => false, 'error' => 'report_not_found'], 404);
        }

        // 🔒 seul celui qui a signalé peut annuler
        if ($report->getReporter()?->getId() !== $me->getId()) {
            throw new AccessDeniedException();
        }

        // déjà annulé / résolu
        if ($report->getStatus() !== Report::STATUS_OPEN) {
            return $this->json(['ok' => true, 'alreadyClosed' => true]);
        }

        $report->cancel(); // met status=canceled + canceledAt
        $this->em->flush();

        return $this->json(['ok' => true]);
    }
}
