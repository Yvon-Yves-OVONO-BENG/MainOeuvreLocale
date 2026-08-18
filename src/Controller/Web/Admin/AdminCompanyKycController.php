<?php

namespace App\Controller\Web\Admin;

use App\Entity\CompanyKycCase;
use App\Entity\CompanyKycReview;
use App\Repository\CompanyKycCaseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/companies/pending')]
#[IsGranted('ROLE_ADMIN')]
class AdminCompanyKycController extends AbstractController
{
    #[Route('', name: 'admin_companies_pending_index', methods: ['GET'])]
    public function index(CompanyKycCaseRepository $companyKycCaseRepository): Response
    {
        return $this->render('admin/company_kyc.html.twig', [
            'cases' => $companyKycCaseRepository->findFullPendingQueue(),
        ]);
    }

    #[Route('/{id}', name: 'admin_company_kyc_show', methods: ['GET'])]
    public function show(int $id, CompanyKycCaseRepository $companyKycCaseRepository): Response
    {
        $case = $companyKycCaseRepository->findOneWithRelations($id);

        if (!$case) {
            throw $this->createNotFoundException('Dossier KYC introuvable.');
        }

        return $this->render('admin/company_kyc/show.html.twig', [
            'case' => $case,
        ]);
    }

    #[Route('/{id}/status', name: 'admin_company_kyc_update_status', methods: ['POST'])]
    public function updateStatus(
        int $id,
        Request $request,
        CompanyKycCaseRepository $companyKycCaseRepository,
        EntityManagerInterface $em
    ): Response {
        $case = $companyKycCaseRepository->find($id);

        if (!$case) {
            throw $this->createNotFoundException('Dossier KYC introuvable.');
        }

        if (!$this->isCsrfTokenValid('kyc_status_' . $case->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $status = (string) $request->request->get('status');

        $allowedStatuses = [
            CompanyKycCase::STATUS_DRAFT,
            CompanyKycCase::STATUS_PENDING,
            CompanyKycCase::STATUS_IN_REVIEW,
            CompanyKycCase::STATUS_APPROVED,
            CompanyKycCase::STATUS_REJECTED,
        ];

        if (!in_array($status, $allowedStatuses, true)) {
            throw $this->createAccessDeniedException('Statut KYC invalide.');
        }

        $case->setStatus($status);

        if ($status === CompanyKycCase::STATUS_IN_REVIEW) {
            $case->setReviewedBy($this->getUser());
        }

        if (in_array($status, [CompanyKycCase::STATUS_APPROVED, CompanyKycCase::STATUS_REJECTED], true)) {
            $case->setReviewedAt(new \DateTimeImmutable());
            $case->setReviewedBy($this->getUser());
        }

        $em->flush();

        $this->addFlash('success', 'Statut KYC mis à jour.');
        return $this->redirectToRoute('admin_company_kyc_show', ['id' => $case->getId()]);
    }

    #[Route('/{id}/approve', name: 'admin_company_kyc_approve', methods: ['POST'])]
    public function approve(
        int $id,
        Request $request,
        CompanyKycCaseRepository $companyKycCaseRepository,
        EntityManagerInterface $em
    ): Response {
        $case = $companyKycCaseRepository->find($id);

        if (!$case) {
            throw $this->createNotFoundException('Dossier KYC introuvable.');
        }

        if (!$this->isCsrfTokenValid('approve_company_kyc_' . $case->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $case->setStatus(CompanyKycCase::STATUS_APPROVED);
        $case->setReviewedAt(new \DateTimeImmutable());
        $case->setReviewedBy($this->getUser());

        $review = (new CompanyKycReview())
            ->setKycCase($case)
            ->setReviewer($this->getUser())
            ->setDecision(CompanyKycReview::DECISION_APPROVED)
            ->setComment($request->request->get('comment'));

        $em->persist($review);
        $em->flush();

        $this->addFlash('success', 'Dossier KYC approuvé.');
        return $this->redirectToRoute('admin_company_kyc_show', ['id' => $case->getId()]);
    }

    #[Route('/{id}/reject', name: 'admin_company_kyc_reject', methods: ['POST'])]
    public function reject(
        int $id,
        Request $request,
        CompanyKycCaseRepository $companyKycCaseRepository,
        EntityManagerInterface $em
    ): Response {
        $case = $companyKycCaseRepository->find($id);

        if (!$case) {
            throw $this->createNotFoundException('Dossier KYC introuvable.');
        }

        if (!$this->isCsrfTokenValid('reject_company_kyc_' . $case->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $case->setStatus(CompanyKycCase::STATUS_REJECTED);
        $case->setReviewedAt(new \DateTimeImmutable());
        $case->setReviewedBy($this->getUser());
        $case->setAdminNote($request->request->get('comment'));

        $review = (new CompanyKycReview())
            ->setKycCase($case)
            ->setReviewer($this->getUser())
            ->setDecision(CompanyKycReview::DECISION_REJECTED)
            ->setComment($request->request->get('comment'));

        $em->persist($review);
        $em->flush();

        $this->addFlash('warning', 'Dossier KYC rejeté.');
        return $this->redirectToRoute('admin_company_kyc_show', ['id' => $case->getId()]);
    }
}