<?php

namespace App\Controller\Web\Admin;

use App\Entity\SupportTicket;
use App\Form\SupportTicketType;
use App\Repository\SupportTicketRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/support', name: 'admin_support_')]
class AdminSupportController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, SupportTicketRepository $ticketRepository): Response
    {
        $this->denyUnlessAdminOrSuperAdmin();

        $q = trim((string) $request->query->get('q', ''));
        $status = trim((string) $request->query->get('status', ''));
        $priority = trim((string) $request->query->get('priority', ''));

        $tickets = $ticketRepository->findByFilters($q, $status, $priority);

        $stats = $this->buildStats($ticketRepository);

        return $this->render('admin/support/support.html.twig', [
            'stats' => $stats,
            'tickets' => $tickets,
            'filters' => [
                'q' => $q,
                'status' => $status,
                'priority' => $priority,
            ],
            'statuses' => SupportTicket::STATUSES,
            'priorities' => SupportTicket::PRIORITIES,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyUnlessAdminOrSuperAdmin();

        $ticket = new SupportTicket();

        $form = $this->createForm(SupportTicketType::class, $ticket);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($ticket->getOwnerName() === null || trim((string) $ticket->getOwnerName()) === '') {
                $ticket->setOwnerName($this->getUser()?->getUserIdentifier());
            }

            $entityManager->persist($ticket);
            $entityManager->flush();

            $this->addFlash('success', sprintf('Le ticket "%s" a été créé avec succès.', $ticket->getSubject()));

            return $this->redirectToRoute('admin_support_index');
        }

        return $this->render('admin/support/new.html.twig', [
            'form' => $form->createView(),
            'ticket' => $ticket,
            'channels' => SupportTicket::CHANNELS,
            'priorities' => SupportTicket::PRIORITIES,
            'statuses' => SupportTicket::STATUSES,
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(SupportTicket $ticket): Response
    {
        $this->denyUnlessAdminOrSuperAdmin();

        return $this->render('admin/support/show.html.twig', [
            'ticket' => $ticket,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(
        Request $request,
        SupportTicket $ticket,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyUnlessAdminOrSuperAdmin();

        $form = $this->createForm(SupportTicketType::class, $ticket);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', sprintf('Le ticket #%d a été mis à jour.', $ticket->getId()));

            return $this->redirectToRoute('admin_support_show', [
                'id' => $ticket->getId(),
            ]);
        }

        return $this->render('admin/support/edit.html.twig', [
            'form' => $form->createView(),
            'ticket' => $ticket,
            'channels' => SupportTicket::CHANNELS,
            'priorities' => SupportTicket::PRIORITIES,
            'statuses' => SupportTicket::STATUSES,
        ]);
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(
        Request $request,
        SupportTicket $ticket,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyUnlessAdminOrSuperAdmin();

        if ($this->isCsrfTokenValid('delete_support_ticket_' . $ticket->getId(), (string) $request->request->get('_token'))) {
            $entityManager->remove($ticket);
            $entityManager->flush();

            $this->addFlash('success', sprintf('Le ticket #%d a été supprimé.', $ticket->getId()));
        } else {
            $this->addFlash('danger', 'Jeton CSRF invalide. Suppression refusée.');
        }

        return $this->redirectToRoute('admin_support_index');
    }

    private function buildStats(SupportTicketRepository $ticketRepository): array
    {
        $tickets = $ticketRepository->findAll();

        $today = new \DateTimeImmutable('today');
        $tomorrow = $today->modify('+1 day');

        return [
            'total' => $ticketRepository->count([]),
            'open' => $ticketRepository->count([
                'status' => SupportTicket::STATUS_OPEN,
            ]),
            'urgent' => count(array_filter(
                $tickets,
                static fn (SupportTicket $ticket): bool => in_array(
                    $ticket->getPriority(),
                    [SupportTicket::PRIORITY_HIGH, SupportTicket::PRIORITY_CRITICAL],
                    true
                )
            )),
            'resolvedToday' => count(array_filter(
                $tickets,
                static function (SupportTicket $ticket) use ($today, $tomorrow): bool {
                    $updatedAt = $ticket->getUpdatedAt();

                    return $ticket->getStatus() === SupportTicket::STATUS_RESOLVED
                        && $updatedAt !== null
                        && $updatedAt >= $today
                        && $updatedAt < $tomorrow;
                }
            )),
            'avgResponse' => '—',
        ];
    }

    private function denyUnlessAdminOrSuperAdmin(): void
    {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_SUPER_ADMIN')) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }
    }
}