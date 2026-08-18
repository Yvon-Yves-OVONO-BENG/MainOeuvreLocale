<?php

namespace App\Controller\Web\Job;

use App\Entity\Application;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class ApplicationStatusToggleController extends AbstractController
{
    #[Route('/talent/publisher/applications/{id}/toggle', name: 'talent_app_toggle', methods: ['POST'])]
    public function __invoke(Application $app, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $me = $this->getUser();
        if (!$me instanceof User) {
            return $this->json(['ok' => false, 'message' => 'Accès refusé.'], 403);
        }

        $job = $app->getJob();

        // 🔒 sécurité : seul le propriétaire du job
        if (method_exists($job, 'getUser') && $job->getCreatedBy() !== $me) {
            return $this->json(['ok' => false, 'message' => 'Accès refusé.'], 403);
        }

        $data = json_decode($request->getContent() ?: '{}', true);
        $token = $data['_token'] ?? '';
        if (!$this->isCsrfTokenValid('app_toggle_'.$app->getId(), $token)) {
            return $this->json(['ok' => false, 'message' => 'Action non autorisée.'], 400);
        }

        $next = $data['next'] ?? null; // 'ACCEPTED' | 'REJECTED'

        // 🔁 adapte aux constantes de ton Application
        if ($next === 'ACCEPTED') {
            $app->setStatus('ACCEPTED');
        } elseif ($next === 'REJECTED') {
            $app->setStatus('REJECTED');
        } else {
            return $this->json(['ok' => false, 'message' => 'Statut invalide.'], 400);
        }

        $em->flush();

        return $this->json([
            'ok' => true,
            'status' => $app->getStatus(),
            'message' => $next === 'ACCEPTED' ? 'Candidature acceptée ✅' : 'Candidature rejetée ❌',
        ]);
    }
}