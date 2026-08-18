<?php

namespace App\Controller\Web\Admin;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\{Request, Response, JsonResponse};
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/broadcast', name: 'admin_broadcast_')]
#[IsGranted('ROLE_ADMIN')]
class AdminBroadcastController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/broadcast_index.html.twig', [
            'csrf' => $this->container->get('security.csrf.token_manager')->getToken('admin_broadcast')->getValue(),
            'rolesList' => ['ROLE_TALENT','ROLE_COMPANY','ROLE_PARTICULIER'],
        ]);
    }

    #[Route('/preview', name: 'preview', methods: ['POST'])]
    public function preview(Request $request, UserRepository $users): JsonResponse
    {
        $csrf = (string) $request->headers->get('X-CSRF-TOKEN', '');
        if (!$this->isCsrfTokenValid('admin_broadcast', $csrf)) {
            return $this->json(['ok'=>false,'error'=>'CSRF invalide'], 403);
        }

        $p = json_decode((string)$request->getContent(), true) ?: [];
        [$count, $where] = $this->countRecipients($users, $p);

        return $this->json(['ok'=>true,'count'=>$count,'hint'=>$where]);
    }

    #[Route('/send', name: 'send', methods: ['POST'])]
    public function send(Request $request, UserRepository $users /*, BroadcastService $svc */): JsonResponse
    {
        $csrf = (string) $request->headers->get('X-CSRF-TOKEN', '');
        if (!$this->isCsrfTokenValid('admin_broadcast', $csrf)) {
            return $this->json(['ok'=>false,'error'=>'CSRF invalide'], 403);
        }

        $p = json_decode((string)$request->getContent(), true) ?: [];
        $subject = trim((string)($p['subject'] ?? ''));
        $body    = trim((string)($p['body'] ?? ''));

        if (mb_strlen($subject) < 3) return $this->json(['ok'=>false,'error'=>'Sujet trop court'], 400);
        if (mb_strlen($body) < 10) return $this->json(['ok'=>false,'error'=>'Message trop court'], 400);

        [$count] = $this->countRecipients($users, $p);

        // ✅ Ici tu branches ton vrai envoi : notifications in-app / email / etc.
        // Exemple:
        // $svc->sendBroadcast($subject, $body, $p);

        return $this->json(['ok'=>true,'sent'=>$count]);
    }

    private function countRecipients(UserRepository $users, array $p): array
    {
        $role = (string)($p['role'] ?? '');
        $onlyActive = (bool)($p['onlyActive'] ?? true);

        $qb = $users->createQueryBuilder('u')->select('COUNT(u.id)');

        if ($onlyActive) $qb->andWhere('u.isActive = 1');

        if ($role !== '') {
            $qb->andWhere('u.roles LIKE :role')->setParameter('role', '%"'.$role.'"%');
            $hint = "Role = {$role}";
        } else {
            $hint = "Tous";
        }

        $count = (int)$qb->getQuery()->getSingleScalarResult();
        return [$count, $hint];
    }
}