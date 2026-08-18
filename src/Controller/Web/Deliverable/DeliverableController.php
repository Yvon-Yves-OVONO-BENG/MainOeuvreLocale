<?php

namespace App\Controller\Web\Deliverable;

use App\Entity\Deliverable;
use App\Entity\User;
use App\Form\DeliverableType;
use App\Repository\DeliverableRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/deliverables', name: 'deliverable_')]
class DeliverableController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private DeliverableRepository $repo
    ) {}

    private function me(): User
    {
        $this->denyAccessUnlessGranted('ROLE_USER');
        $u = $this->getUser();
        if (!$u instanceof User) throw $this->createAccessDeniedException();
        return $u;
    }

    private function assertOwner(Deliverable $d): void
    {
        if ($d->getOwner()?->getId() !== $this->me()->getId()) {
            throw $this->createAccessDeniedException("Accès refusé.");
        }
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $me = $this->me();
        $status = $request->query->get('status');
        $items = $this->repo->findForUser($me, $status ?: null);
        $kpis  = $this->repo->countByStatusForUser($me);

        return $this->render('deliverable/deliverable.html.twig', [
            'items' => $items,
            'kpis' => $kpis,
            'status' => $status,
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET','POST'])]
    public function new(Request $request): Response
    {
        $me = $this->me();

        $d = new Deliverable();
        $d->setOwner($me);

        $form = $this->createForm(DeliverableType::class, $d);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $d->touch();
            $this->em->persist($d);
            $this->em->flush();

            $this->addFlash('success', 'Livrable créé ✅');
            return $this->redirectToRoute('deliverable_index');
        }

        return $this->render('deliverable/form.html.twig', [
            'form' => $form,
            'mode' => 'create',
            'item' => $d,
        ]);
    }

    #[Route('/{id}/edit', name: 'edit', methods: ['GET','POST'])]
    public function edit(Request $request, Deliverable $d): Response
    {
        $this->assertOwner($d);

        if (in_array($d->getStatus(), [Deliverable::STATUS_VALIDATED, Deliverable::STATUS_SUBMITTED], true)) {
            $this->addFlash('warning', "Ce livrable n'est plus modifiable pour le moment.");
            return $this->redirectToRoute('deliverable_index');
        }

        $form = $this->createForm(DeliverableType::class, $d);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $d->touch();
            $this->em->flush();
            $this->addFlash('success', 'Modifications enregistrées ✅');
            return $this->redirectToRoute('deliverable_index');
        }

        return $this->render('deliverable/form.html.twig', [
            'form' => $form,
            'mode' => 'edit',
            'item' => $d,
        ]);
    }

    #[Route('/{id}/submit', name: 'submit', methods: ['POST'])]
    public function submit(Request $request, Deliverable $d): Response
    {
        $this->assertOwner($d);
        if (!$this->isCsrfTokenValid('submit_deliverable_'.$d->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('CSRF invalide');
        }

        $d->setStatus(Deliverable::STATUS_SUBMITTED);
        $d->setSubmittedAt(new \DateTimeImmutable());
        $d->touch();
        $this->em->flush();

        $this->addFlash('success', 'Livrable soumis pour validation 🚀');
        return $this->redirectToRoute('deliverable_index');
    }

    #[Route('/{id}/validate', name: 'validate', methods: ['POST'])]
    public function validate(Request $request, Deliverable $d): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('validate_deliverable_'.$d->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('CSRF invalide');
        }

        $note = trim((string) $request->request->get('note', ''));

        $d->setStatus(Deliverable::STATUS_VALIDATED);
        $d->setValidatedAt(new \DateTimeImmutable());
        $d->setValidatedBy($this->me());
        $d->setValidationNote($note ?: null);
        $d->touch();
        $this->em->flush();

        $this->addFlash('success', 'Livrable validé ✅');
        return $this->redirectToRoute('deliverable_index');
    }

    #[Route('/{id}/reject', name: 'reject', methods: ['POST'])]
    public function reject(Request $request, Deliverable $d): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if (!$this->isCsrfTokenValid('reject_deliverable_'.$d->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('CSRF invalide');
        }

        $note = trim((string) $request->request->get('note', ''));

        $d->setStatus(Deliverable::STATUS_REJECTED);
        $d->setValidatedAt(null);
        $d->setValidatedBy(null);
        $d->setValidationNote($note ?: 'À corriger puis resoumettre.');
        $d->touch();
        $this->em->flush();

        $this->addFlash('warning', 'Livrable rejeté ❌ (corrige puis resoumets)');
        return $this->redirectToRoute('deliverable_index');
    }

    #[Route('/{id}', name: 'delete', methods: ['POST'])]
    public function delete(Request $request, Deliverable $d): Response
    {
        $this->assertOwner($d);

        if (!$this->isCsrfTokenValid('delete_deliverable_'.$d->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('CSRF invalide');
        }

        if ($d->getStatus() === Deliverable::STATUS_VALIDATED) {
            $this->addFlash('warning', "Impossible de supprimer un livrable validé.");
            return $this->redirectToRoute('deliverable_index');
        }

        $this->em->remove($d);
        $this->em->flush();

        $this->addFlash('success', 'Livrable supprimé.');
        return $this->redirectToRoute('deliverable_index');
    }
}