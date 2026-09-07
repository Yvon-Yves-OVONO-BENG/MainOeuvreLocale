<?php

namespace App\Controller\Web;

use App\Entity\DocumentAccessGrant;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/documents/access', name: 'document_access_')]
final class DocumentAccessController extends AbstractController
{
    public function __construct(#[Autowire('%kernel.project_dir%')] private readonly string $projectDir) {}

    #[Route('/request/{id}/{type}', name: 'request', requirements: ['type' => 'cv|cni'], methods: ['POST'])]
    public function requestAccess(User $owner, string $type, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $viewer = $this->getUser();
        if (!$viewer instanceof User || $viewer->getId() === $owner->getId()) return $this->json(['ok' => false], 403);
        if (!$this->isCsrfTokenValid('document_access_request_'.$owner->getId(), (string) $request->request->get('_token'))) return $this->json(['ok' => false], 403);
        $grant = $em->getRepository(DocumentAccessGrant::class)->findOneBy(['owner' => $owner, 'viewer' => $viewer, 'documentType' => $type]) ?? (new DocumentAccessGrant())->setOwner($owner)->setViewer($viewer)->setDocumentType($type);
        $grant->setStatus('pending')->setDecidedAt(null)->setExpiresAt(null); $em->persist($grant); $em->flush();
        return $this->json(['ok' => true, 'message' => 'Demande envoyée au propriétaire du document.']);
    }

    #[Route('/decision/{id}/{decision}', name: 'decision', requirements: ['decision' => 'approve|reject|revoke'], methods: ['POST'])]
    public function decision(DocumentAccessGrant $grant, string $decision, Request $request, EntityManagerInterface $em): JsonResponse
    {
        $me = $this->getUser();
        if (!$me instanceof User || $grant->getOwner()?->getId() !== $me->getId()) return $this->json(['ok' => false], 403);
        if (!$this->isCsrfTokenValid('document_access_decision_'.$grant->getId(), (string) $request->request->get('_token'))) return $this->json(['ok' => false], 403);
        $grant->setStatus($decision === 'approve' ? 'approved' : ($decision === 'reject' ? 'rejected' : 'revoked'))->setDecidedAt(new \DateTimeImmutable())->setExpiresAt($decision === 'approve' ? new \DateTimeImmutable('+30 days') : null);
        $em->flush(); return $this->json(['ok' => true, 'status' => $grant->getStatus()]);
    }

    #[Route('/view/{id}', name: 'view', methods: ['GET'])]
    public function view(DocumentAccessGrant $grant, EntityManagerInterface $em): BinaryFileResponse
    {
        $me = $this->getUser();
        if (!$me instanceof User || $grant->getViewer()?->getId() !== $me->getId() || !$grant->isUsable()) throw $this->createAccessDeniedException();
        $owner = $grant->getOwner();
        $stored = $grant->getDocumentType() === 'cni' ? $owner?->getPersonalProfile()?->getCni() : $owner?->getProfessionalProfile()?->getCv();
        if (!$stored || basename($stored) !== $stored) throw $this->createNotFoundException();
        $folders = $grant->getDocumentType() === 'cni' ? ['public/uploads/cni','public/uploads/profiles/cni','public/uploads/documents'] : ['public/uploads/cv','public/uploads/profiles/cv','public/uploads/documents'];
        $file = null; foreach ($folders as $folder) { $candidate = $this->projectDir.'/'.$folder.'/'.$stored; if (is_file($candidate)) { $file = $candidate; break; } }
        if (!$file) throw $this->createNotFoundException();
        $grant->registerAccess(); $em->flush();
        return $this->file($file, $stored, ['Content-Security-Policy' => "default-src 'none'; sandbox"]);
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(EntityManagerInterface $em): Response
    {
        $me = $this->getUser();
        if (!$me instanceof User) throw $this->createAccessDeniedException();
        return $this->render('profile/document_access.html.twig', [
            'received' => $em->getRepository(DocumentAccessGrant::class)->findBy(['owner' => $me], ['requestedAt' => 'DESC']),
            'sent' => $em->getRepository(DocumentAccessGrant::class)->findBy(['viewer' => $me], ['requestedAt' => 'DESC']),
        ]);
    }
}
