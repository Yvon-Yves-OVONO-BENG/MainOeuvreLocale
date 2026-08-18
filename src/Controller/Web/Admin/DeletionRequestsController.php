<?php

namespace App\Controller\Web\Admin;

use App\Entity\AccountDeletionRequest;
use App\Entity\User;
use App\Repository\AccountDeletionRequestRepository;
use App\Service\UserActivityLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class DeletionRequestsController extends AbstractController
{
    #[Route('/admin/deletion-requests', name: 'admin_deletion_requests', methods: ['GET'])]
    public function index(Request $request, AccountDeletionRequestRepository $repo): Response
    {
        $status = (string) $request->query->get('status', '');
        $q = trim((string) $request->query->get('q', ''));

        $items = $repo->searchAdmin($status ?: null, $q ?: null, 120);

        return $this->render('admin/deletion_requests.html.twig', [
            'items' => $items,
            'filters' => ['status' => $status, 'q' => $q],
        ]);
    }

    #[Route('/admin/deletion-requests/{id}/approve', name: 'admin_deletion_requests_approve', methods: ['POST'])]
    public function approve(
        int $id,
        Request $request,
        AccountDeletionRequestRepository $repo,
        EntityManagerInterface $em,
        CsrfTokenManagerInterface $csrf,
        UserActivityLogger $logger
    ): Response {
        $token = (string) $request->request->get('_token', '');
        if (!$csrf->isTokenValid(new CsrfToken('delreq_approve_' . $id, $token))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_deletion_requests');
        }

        /** @var AccountDeletionRequest|null $r */
        $r = $repo->find($id);
        if (!$r) {
            $this->addFlash('error', 'Demande introuvable.');
            return $this->redirectToRoute('admin_deletion_requests');
        }

        /** @var User $admin */
        $admin = $this->getUser();

        $days = (int) $request->request->get('days', 7);
        if ($days < 0) $days = 0;
        if ($days > 60) $days = 60;

        $note = trim((string) $request->request->get('note', ''));

        $r->setStatus(AccountDeletionRequest::STATUS_APPROVED)
          ->setApprovedAt(new \DateTimeImmutable())
          ->setScheduledAt((new \DateTimeImmutable())->modify("+{$days} days"))
          ->setHandledBy($admin)
          ->setAdminNote($note ?: null);

        // Désactivation immédiate
        $u = $r->getUser();
        $u->setIsActive(false);
        if (method_exists($u, 'setUpdatedAt')) $u->setUpdatedAt(new \DateTime());

        $em->flush();

        $logger->log($admin, 'admin.deletion.approved', $request, [
            'requestId' => $r->getId(),
            'userId' => $u->getId(),
            'scheduledInDays' => $days,
        ]);

        $this->addFlash('success', "Approuvé ✅ Suppression (anonymisation) programmée dans {$days} jour(s).");
        return $this->redirectToRoute('admin_deletion_requests', ['status' => AccountDeletionRequest::STATUS_PENDING]);
    }

    #[Route('/admin/deletion-requests/{id}/reject', name: 'admin_deletion_requests_reject', methods: ['POST'])]
    public function reject(
        int $id,
        Request $request,
        AccountDeletionRequestRepository $repo,
        EntityManagerInterface $em,
        CsrfTokenManagerInterface $csrf,
        UserActivityLogger $logger
    ): Response {
        $token = (string) $request->request->get('_token', '');
        if (!$csrf->isTokenValid(new CsrfToken('delreq_reject_' . $id, $token))) {
            $this->addFlash('error', 'Token CSRF invalide.');
            return $this->redirectToRoute('admin_deletion_requests');
        }

        /** @var AccountDeletionRequest|null $r */
        $r = $repo->find($id);
        if (!$r) {
            $this->addFlash('error', 'Demande introuvable.');
            return $this->redirectToRoute('admin_deletion_requests');
        }

        /** @var User $admin */
        $admin = $this->getUser();

        $note = trim((string) $request->request->get('note', ''));

        $r->setStatus(AccountDeletionRequest::STATUS_REJECTED)
          ->setRejectedAt(new \DateTimeImmutable())
          ->setHandledBy($admin)
          ->setAdminNote($note ?: null);

        // Option : réactiver le compte si tu rejettes
        $u = $r->getUser();
        $u->setIsActive(true);
        if (method_exists($u, 'setUpdatedAt')) $u->setUpdatedAt(new \DateTime());

        $em->flush();

        $logger->log($admin, 'admin.deletion.rejected', $request, [
            'requestId' => $r->getId(),
            'userId' => $u->getId(),
        ]);

        $this->addFlash('success', 'Demande rejetée ✅ Compte réactivé.');
        return $this->redirectToRoute('admin_deletion_requests');
    }
}