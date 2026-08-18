<?php

namespace App\Service;

use App\Entity\PaymentDispute;
use App\Entity\User;
use App\Repository\PaymentDisputeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Contracts\Translation\TranslatorInterface;

class TalentPaymentDisputeService
{
    public function __construct(
        private readonly PaymentDisputeRepository $paymentDisputeRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator
    ) {
    }

    public function createDisputeDraft(User $user): PaymentDispute
    {
        $paymentDispute = new PaymentDispute();
        $paymentDispute->setUser($user);

        return $paymentDispute;
    }

    public function getIndexData(User $user): array
    {
        return [
            'recentDisputes' => $this->paymentDisputeRepository->findRecentForUser($user, 5),
            'disputes' => $this->paymentDisputeRepository->findAllForUser($user),
            'totalCount' => $this->paymentDisputeRepository->countAllForUser($user),
            'openCount' => $this->paymentDisputeRepository->countByStatusForUser($user, PaymentDispute::STATUS_OPEN),
            'reviewCount' => $this->paymentDisputeRepository->countByStatusForUser($user, PaymentDispute::STATUS_REVIEW),
            'resolvedCount' => $this->paymentDisputeRepository->countByStatusForUser($user, PaymentDispute::STATUS_RESOLVED),
            'rejectedCount' => $this->paymentDisputeRepository->countByStatusForUser($user, PaymentDispute::STATUS_REJECTED),
            'refundedCount' => $this->paymentDisputeRepository->countByStatusForUser($user, PaymentDispute::STATUS_REFUNDED),
        ];
    }

    public function getNewPageData(User $user): array
    {
        return [
            'recentDisputes' => $this->paymentDisputeRepository->findRecentForUser($user, 5),
            'totalCount' => $this->paymentDisputeRepository->countAllForUser($user),
            'openCount' => $this->paymentDisputeRepository->countByStatusForUser($user, PaymentDispute::STATUS_OPEN),
            'reviewCount' => $this->paymentDisputeRepository->countByStatusForUser($user, PaymentDispute::STATUS_REVIEW),
        ];
    }

    public function submitNewDispute(PaymentDispute $paymentDispute, User $user): array
    {
        $paymentDispute->setUser($user);

        $payment = $paymentDispute->getPayment();

        if (null === $payment) {
            throw new BadRequestHttpException(
                $this->translator->trans('Veuillez sélectionner un paiement.')
            );
        }

        $existing = $this->paymentDisputeRepository->findOpenForUserAndPayment($user, $payment);

        if ($existing) {
            throw new ConflictHttpException(
                $this->translator->trans('Un signalement ouvert existe déjà pour ce paiement.')
            );
        }

        if (method_exists($payment, 'getInvoices')) {
            $invoices = $payment->getInvoices();

            if ($invoices && method_exists($invoices, 'first')) {
                $firstInvoice = $invoices->first();
                if ($firstInvoice) {
                    $paymentDispute->setInvoice($firstInvoice);
                }
            }
        }

        $this->entityManager->persist($paymentDispute);
        $this->entityManager->flush();

        return [
            'ok' => true,
            'message' => $this->translator->trans('Votre signalement de paiement a bien été envoyé.'),
            'dispute' => $paymentDispute,
        ];
    }

    public function getApiIndexPayload(User $user): array
    {
        $data = $this->getIndexData($user);

        return [
            'ok' => true,
            'recentDisputes' => array_map(
                fn ($dispute) => $this->formatDispute($dispute),
                $data['recentDisputes']
            ),
            'disputes' => array_map(
                fn ($dispute) => $this->formatDispute($dispute),
                $data['disputes']
            ),
            'stats' => [
                'totalCount' => $data['totalCount'],
                'openCount' => $data['openCount'],
                'reviewCount' => $data['reviewCount'],
                'resolvedCount' => $data['resolvedCount'],
                'rejectedCount' => $data['rejectedCount'],
                'refundedCount' => $data['refundedCount'],
            ],
        ];
    }

    public function getApiNewPayload(User $user): array
    {
        $data = $this->getNewPageData($user);

        return [
            'ok' => true,
            'recentDisputes' => array_map(
                fn ($dispute) => $this->formatDispute($dispute),
                $data['recentDisputes']
            ),
            'stats' => [
                'totalCount' => $data['totalCount'],
                'openCount' => $data['openCount'],
                'reviewCount' => $data['reviewCount'],
            ],
        ];
    }

    private function formatDispute(object $dispute): array
    {
        return [
            'id' => method_exists($dispute, 'getId') ? $dispute->getId() : null,
            'status' => method_exists($dispute, 'getStatus') ? $dispute->getStatus() : null,
            'subject' => method_exists($dispute, 'getSubject') ? $dispute->getSubject() : null,
            'reason' => method_exists($dispute, 'getReason') ? $dispute->getReason() : null,
            'message' => method_exists($dispute, 'getMessage') ? $dispute->getMessage() : null,
            'createdAt' => method_exists($dispute, 'getCreatedAt') && $dispute->getCreatedAt()
                ? $dispute->getCreatedAt()->format(\DateTimeInterface::ATOM)
                : null,
            'paymentId' => method_exists($dispute, 'getPayment') && $dispute->getPayment() && method_exists($dispute->getPayment(), 'getId')
                ? $dispute->getPayment()->getId()
                : null,
            'invoiceId' => method_exists($dispute, 'getInvoice') && $dispute->getInvoice() && method_exists($dispute->getInvoice(), 'getId')
                ? $dispute->getInvoice()->getId()
                : null,
        ];
    }
}