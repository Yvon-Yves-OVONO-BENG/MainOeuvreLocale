<?php

namespace App\Controller\Web\Admin;

use App\Entity\PaymentDispute;
use App\Entity\User;
use App\Form\PaymentDisputeType;
use App\Repository\PaymentDisputeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/payments/disputes', name: 'admin_payment_dispute_')]
class AdminPaymentDisputeController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, PaymentDisputeRepository $repo): Response
    {
        $filters = [
            'q' => trim((string) $request->query->get('q', '')),
            'status' => trim((string) $request->query->get('status', '')),
            'priority' => trim((string) $request->query->get('priority', '')),
            'source' => trim((string) $request->query->get('source', '')),
        ];

        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = min(100, max(10, (int) $request->query->get('perPage', 20)));
        $offset = ($page - 1) * $perPage;

        $qb = $repo->qbAdminList($filters);

        $total = (int) (clone $qb)
            ->select('COUNT(DISTINCT d.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $qb->setFirstResult($offset)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        $pages = max(1, (int) ceil($total / $perPage));

        return $this->render('admin/payment_dispute_index.html.twig', [
            'items' => $items,
            'filters' => $filters,
            'page' => $page,
            'pages' => $pages,
            'perPage' => $perPage,
            'total' => $total,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $dispute = new PaymentDispute();
        $dispute->setSource(PaymentDispute::SOURCE_ADMIN);

        $form = $this->createForm(PaymentDisputeType::class, $dispute, [
            'is_admin' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($dispute->getPayment() && !$dispute->getUser()) {
                $dispute->setUser($dispute->getPayment()->getUser());
            }

            if ($this->getUser() instanceof User) {
                $dispute->setHandledBy($this->getUser());
            }

            if ($dispute->getStatus() === PaymentDispute::STATUS_RESOLVED) {
                $dispute->setResolvedAt(new \DateTime());
            }

            $em->persist($dispute);
            $em->flush();

            $this->addFlash('success', 'Litige créé avec succès.');

            return $this->redirectToRoute('admin_payment_dispute_index');
        }

        return $this->render('admin/payment_dispute_new.html.twig', [
            'form' => $form->createView(),
        ]);
    }


    #[Route('/{id}/set-status', name: 'set_status', methods: ['POST'])]
    public function setStatus(
        Request $request,
        PaymentDispute $dispute,
        EntityManagerInterface $em
    ): JsonResponse {
        $status = trim((string) $request->request->get('status', ''));

        if (!in_array($status, [
            PaymentDispute::STATUS_OPEN,
            PaymentDispute::STATUS_REVIEW,
            PaymentDispute::STATUS_RESOLVED,
            PaymentDispute::STATUS_REJECTED,
            PaymentDispute::STATUS_REFUNDED,
        ], true)) {
            return $this->json(['ok' => false, 'error' => 'Statut invalide'], 400);
        }

        $dispute->setStatus($status);

        if (in_array($status, [
            PaymentDispute::STATUS_RESOLVED,
            PaymentDispute::STATUS_REFUNDED,
        ], true)) {
            $dispute->setResolvedAt(new \DateTime());
        } else {
            $dispute->setResolvedAt(null);
        }

        if ($this->getUser() instanceof User) {
            $dispute->setHandledBy($this->getUser());
        }

        $em->flush();

        return $this->json([
            'ok' => true,
            'status' => $dispute->getStatus(),
        ]);
    }


    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(PaymentDispute $dispute): Response
    {
        return $this->render('admin/payment_dispute_show.html.twig', [
            'dispute' => $dispute,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        PaymentDispute $dispute,
        EntityManagerInterface $em
    ): Response {
        $form = $this->createForm(PaymentDisputeType::class, $dispute, [
            'is_admin' => true,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (
                method_exists(PaymentDispute::class, 'STATUS_REFUNDED') &&
                $dispute->getStatus() === PaymentDispute::STATUS_REFUNDED
            ) {
                $dispute->setResolvedAt(new \DateTime());
            }

            if ($dispute->getStatus() === PaymentDispute::STATUS_RESOLVED) {
                $dispute->setResolvedAt(new \DateTime());
            }

            if ($this->getUser() instanceof User) {
                $dispute->setHandledBy($this->getUser());
            }

            $em->flush();

            $this->addFlash('success', 'Litige modifié avec succès.');

            return $this->redirectToRoute('admin_payment_dispute_show', [
                'id' => $dispute->getId(),
            ]);
        }

        return $this->render('admin/payment_dispute_edit.html.twig', [
            'dispute' => $dispute,
            'form' => $form->createView(),
        ]);
    }
}