<?php
// src/Controller/Moderateur/AppealsController.php

namespace App\Controller\Web\Moderateur;

use App\Repository\AppealRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/moderateur/appeals', name: 'moderateur_appeals_')]
#[IsGranted(new Expression('is_granted("ROLE_MODERATEUR") or is_granted("ROLE_ADMIN") or is_granted("ROLE_SUPER_ADMIN")'))]
class AppealsController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, AppealRepository $repo): Response
    {
        $q      = trim((string)$request->query->get('q',''));
        $status = (string)$request->query->get('status','pending'); // pending|accepted|rejected|all
        $role   = (string)$request->query->get('role','');          // talent|particulier|company|''
        $days   = max(7, min(180, (int)$request->query->get('days', 30)));

        $page   = max(1, (int)$request->query->get('page', 1));
        $limit  = min(50, max(10, (int)$request->query->get('limit', 10)));
        $offset = ($page - 1) * $limit;

        $res = $repo->findForIndex($q, $status, $role, $days, $limit, $offset);
        $stats = $repo->stats($days);

        return $this->render('moderateur/appeals.html.twig', [
            'items' => $res['items'],
            'total' => $res['total'],
            'page'  => $page,
            'pages' => max(1, (int)ceil($res['total'] / $limit)),
            'limit' => $limit,
            'q'     => $q,
            'status'=> $status,
            'role'  => $role,
            'days'  => $days,
            'stats' => $stats,
        ]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id'=>'\d+'], methods: ['GET'])]
    public function show(int $id, AppealRepository $repo): Response
    {
        $a = $repo->findOneWithUserProfiles($id);
        if (!$a) throw new NotFoundHttpException('Appel introuvable.');

        return $this->render('moderateur/appeals_show.html.twig', [
            'a' => $a,
            'u' => $a->getUser(),
            'pp'=> $a->getUser()?->getPersonalProfile(),
            'pro'=> $a->getUser()?->getProfessionalProfile(),
        ]);
    }

    #[Route('/{id}/accept', name: 'accept', requirements: ['id'=>'\d+'], methods: ['POST'])]
    public function accept(int $id, Request $request, AppealRepository $repo): JsonResponse
    {
        $payload = json_decode((string)$request->getContent(), true) ?: [];
        if (!$this->isCsrfTokenValid('appeal_accept_'.$id, (string)($payload['_token'] ?? ''))) {
            return $this->json(['ok'=>false,'message'=>'CSRF invalide'], 403);
        }

        $note = trim((string)($payload['note'] ?? '')) ?: null;

        /**
         * @var User
         */
        $user = $this->getUser();
        $repo->markDecision($id, \App\Entity\Appeal::STATUS_ACCEPTED, (int)$user->getId(), $note);

        return $this->json(['ok'=>true]);
    }

    #[Route('/{id}/reject', name: 'reject', requirements: ['id'=>'\d+'], methods: ['POST'])]
    public function reject(int $id, Request $request, AppealRepository $repo): JsonResponse
    {
        $payload = json_decode((string)$request->getContent(), true) ?: [];
        if (!$this->isCsrfTokenValid('appeal_reject_'.$id, (string)($payload['_token'] ?? ''))) {
            return $this->json(['ok'=>false,'message'=>'CSRF invalide'], 403);
        }

        $note = trim((string)($payload['note'] ?? '')) ?: null;

        /**
         * @var User
         */
        $user = $this->getUser();
        $repo->markDecision($id, \App\Entity\Appeal::STATUS_REJECTED, (int)$user->getId(), $note);

        return $this->json(['ok'=>true]);
    }

    #[Route('/{id}/note', name: 'note', requirements: ['id'=>'\d+'], methods: ['POST'])]
    public function note(int $id, Request $request, AppealRepository $repo): JsonResponse
    {
        $payload = json_decode((string)$request->getContent(), true) ?: [];
        if (!$this->isCsrfTokenValid('appeal_note_'.$id, (string)($payload['_token'] ?? ''))) {
            return $this->json(['ok'=>false,'message'=>'CSRF invalide'], 403);
        }

        $note = trim((string)($payload['note'] ?? '')) ?: null;
        $repo->updateNote($id, $note);

        return $this->json(['ok'=>true]);
    }
}