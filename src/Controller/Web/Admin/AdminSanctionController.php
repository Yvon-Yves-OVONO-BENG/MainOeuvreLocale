<?php
// src/Controller/Admin/AdminSanctionController.php

namespace App\Controller\Web\Admin;

use App\Entity\Sanction;
use App\Entity\User;
use App\Form\SanctionType;
use App\Repository\SanctionRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/sanctions', name: 'admin_sanction_')]
class AdminSanctionController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, SanctionRepository $sanctionRepository): Response
    {
        $filters = [
            'q' => trim((string) $request->query->get('q', '')),
            'type' => trim((string) $request->query->get('type', '')),
            'status' => trim((string) $request->query->get('status', '')),
            'hidden' => (string) $request->query->get('hidden', ''),
        ];

        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = min(100, max(10, (int) $request->query->get('perPage', 15)));
        $offset = ($page - 1) * $perPage;

        $qb = $sanctionRepository->qbAdminList($filters);

        $total = (int) (clone $qb)
            ->select('COUNT(DISTINCT s.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $qb->setFirstResult($offset)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        $pages = max(1, (int) ceil($total / $perPage));

        return $this->render('admin/sanction_index.html.twig', [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'perPage' => $perPage,
            'filters' => $filters,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $em
    ): Response {
        $sanction = new Sanction();

        $userId = $request->query->get('user');
        if ($userId && ctype_digit((string) $userId)) {
            $user = $userRepository->find((int) $userId);
            if ($user instanceof User) {
                $sanction->setUser($user);
            }
        }

        if (method_exists($sanction, 'setCreatedBy') && $this->getUser() instanceof User) {
            $sanction->setCreatedBy($this->getUser());
        }

        $form = $this->createForm(SanctionType::class, $sanction);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (method_exists($sanction, 'setCreatedAt') && null === $sanction->getCreatedAt()) {
                $sanction->setCreatedAt(new \DateTimeImmutable());
            }

            $em->persist($sanction);
            $em->flush();

            $this->addFlash('success', 'Sanction créée avec succès.');

            return $this->redirectToRoute('admin_sanction_index');
        }

        return $this->render('admin/sanction_new.html.twig', [
            'form' => $form->createView(),
            'prefilledUser' => method_exists($sanction, 'getUser') ? $sanction->getUser() : null,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        Sanction $sanction,
        EntityManagerInterface $em
    ): Response {
        $form = $this->createForm(SanctionType::class, $sanction);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Sanction modifiée avec succès.');

            return $this->redirectToRoute('admin_sanction_index');
        }

        return $this->render('admin/sanction_new.html.twig', [
            'form' => $form->createView(),
            'prefilledUser' => method_exists($sanction, 'getUser') ? $sanction->getUser() : null,
            'editMode' => true,
            'sanction' => $sanction,
        ]);
    }

    #[Route('/{id}/revoke', name: 'revoke', methods: ['POST'])]
    public function revoke(
        Request $request,
        Sanction $sanction,
        EntityManagerInterface $em
    ): JsonResponse {
        if (!$this->isCsrfTokenValid('revoke_sanction_' . $sanction->getId(), (string) $request->request->get('_token'))) {
            return $this->json(['ok' => false, 'error' => 'Jeton CSRF invalide.'], 400);
        }

        if (method_exists($sanction, 'revoke')) {
            $sanction->revoke();
        } else {
            $sanction->setStatus('revoked');
        }

        $em->flush();

        return $this->json([
            'ok' => true,
            'status' => $sanction->getStatus(),
        ]);
    }

    #[Route('/{id}/toggle-hidden', name: 'toggle_hidden', methods: ['POST'])]
    public function toggleHidden(
        Request $request,
        Sanction $sanction,
        EntityManagerInterface $em
    ): JsonResponse {
        if (!$this->isCsrfTokenValid('toggle_hidden_sanction_' . $sanction->getId(), (string) $request->request->get('_token'))) {
            return $this->json(['ok' => false, 'error' => 'Jeton CSRF invalide.'], 400);
        }

        $sanction->setSupprimer(!$sanction->isSupprimer());
        $em->flush();

        return $this->json([
            'ok' => true,
            'hidden' => $sanction->isSupprimer(),
        ]);
    }
}