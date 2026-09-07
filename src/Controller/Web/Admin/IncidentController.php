<?php

namespace App\Controller\Web\Admin;

use App\Entity\Incident;
use App\Repository\IncidentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/incidents-techniques', name: 'admin_incidents_')]
final class IncidentController extends AbstractController
{
    public function __construct(
        private readonly IncidentRepository $incidents,
        private readonly EntityManagerInterface $em,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $this->denyUnlessAdmin();
        $items = $this->incidents->findLatest(300);

        return $this->render('admin/incidents/index.html.twig', [
            'incidents' => $items,
            'openCount' => $this->incidents->countOpen(),
            'totalCount' => $this->incidents->count([]),
        ]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(Incident $incident): Response
    {
        $this->denyUnlessAdmin();

        return $this->render('admin/incidents/show.html.twig', [
            'incident' => $incident,
        ]);
    }

    #[Route('/{id}/resoudre', name: 'resolve', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function resolve(Incident $incident, Request $request): Response
    {
        $this->denyUnlessAdmin();
        if (!$this->isCsrfTokenValid('incident_resolve_' . $incident->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        $incident->resolve();
        $this->em->flush();
        $this->addFlash('success', 'Incident marqué comme résolu.');
        return $this->redirectToRoute('admin_incidents_index');
    }

    #[Route('/{id}/supprimer', name: 'delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(Incident $incident, Request $request): Response
    {
        $this->denyUnlessAdmin();
        if (!$this->isCsrfTokenValid('incident_delete_' . $incident->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
        $this->em->remove($incident);
        $this->em->flush();
        $this->addFlash('success', 'Incident supprimé.');
        return $this->redirectToRoute('admin_incidents_index');
    }

    #[Route('/purger', name: 'purge', methods: ['POST'])]
    public function purge(Request $request): Response
    {
        $this->denyUnlessAdmin();
        if (!$this->isCsrfTokenValid('incident_purge', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $this->em->createQuery('DELETE FROM App\\Entity\\Incident i')->execute();
        $this->addFlash('success', 'La table des incidents techniques a été purgée.');
        return $this->redirectToRoute('admin_incidents_index');
    }

    private function denyUnlessAdmin(): void
    {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_SUPER_ADMIN')) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }
    }
}
