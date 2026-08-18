<?php

namespace App\Controller\Web\Admin;

use App\Entity\Payment;
use App\Entity\StatusPayment;
use App\Repository\PaymentRepository;
use App\Repository\StatusPaymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/payments/failures', name: 'admin_payments_failures_')]
#[IsGranted('ROLE_ADMIN')]
class AdminPaymentFailuresController extends AbstractController
{
    private const PER_PAGE = 15;

    private const PAYMENT_STATUSES = [
        'INITIE' => 'Le paiement a été créé mais pas encore traité.',
        'EN_ATTENTE' => 'En attente de confirmation du fournisseur de paiement.',
        'SUCCES' => 'Paiement validé et confirmé.',
        'ECHEC' => 'Paiement refusé ou erreur technique.',
        'ANNULE' => 'Paiement annulé par l’utilisateur ou le système.',
        'EXPIRE' => 'Paiement expiré (temps limite dépassé).',
        'REMBOURSE' => 'Paiement remboursé au client.',
        'PARTIEL' => 'Paiement partiellement réglé.',
        'EN_VERIFICATION' => 'Paiement en cours de vérification manuelle.',
        'BLOQUE' => 'Paiement bloqué par le système ou un administrateur.',
    ];

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(PaymentRepository $paymentRepository, Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));

        $baseQb = $paymentRepository->createAdminReviewQueryBuilder();

        $totalItems = count(new Paginator($baseQb->getQuery(), true));
        $totalPages = max(1, (int) ceil($totalItems / self::PER_PAGE));
        $page = min($page, $totalPages);

        $pageQb = $paymentRepository->createAdminReviewQueryBuilder()
            ->setFirstResult(($page - 1) * self::PER_PAGE)
            ->setMaxResults(self::PER_PAGE);

        $pageItems = iterator_to_array(new Paginator($pageQb->getQuery(), true));

        $payments = array_map(
            fn (Payment $payment) => $this->mapPayment($payment),
            $pageItems
        );

        $statusCounts = [];
        foreach (array_keys(self::PAYMENT_STATUSES) as $status) {
            $statusCounts[$status] = 0;
        }

        foreach ($paymentRepository->getAdminPaymentStatusCounts() as $status => $count) {
            $statusCounts[$status] = $count;
        }

        return $this->render('admin/payments/failures_index.html.twig', [
            'payments' => $payments,
            'paymentStatuses' => self::PAYMENT_STATUSES,
            'statusCounts' => $statusCounts,
            'totalPayments' => $totalItems,
            'currentPageCount' => count($payments),
            'pagination' => [
                'page' => $page,
                'perPage' => self::PER_PAGE,
                'totalItems' => $totalItems,
                'totalPages' => $totalPages,
                'hasPrevious' => $page > 1,
                'hasNext' => $page < $totalPages,
                'previousPage' => $page > 1 ? $page - 1 : 1,
                'nextPage' => $page < $totalPages ? $page + 1 : $totalPages,
            ],
        ]);
    }

    #[Route('/{id}/status', name: 'update_status', methods: ['POST'])]
    public function updateStatus(
        Payment $payment,
        Request $request,
        StatusPaymentRepository $statusPaymentRepository,
        EntityManagerInterface $em
    ): JsonResponse {
        if (!$this->isXmlHttpRequest($request)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Requête invalide.',
            ], 400);
        }

        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('admin_payment_status_' . $payment->getId(), $token)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Token CSRF invalide.',
            ], 403);
        }

        $statusCode = strtoupper(trim((string) $request->request->get('status')));
        if (!array_key_exists($statusCode, self::PAYMENT_STATUSES)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Statut invalide.',
            ], 422);
        }

        $statusEntity = $statusPaymentRepository->createQueryBuilder('sp')
            ->andWhere('UPPER(sp.statusPayment) = :status')
            ->setParameter('status', $statusCode)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$statusEntity instanceof StatusPayment) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Le statut demandé n’existe pas en base.',
            ], 404);
        }

        $payment->setSatusPayment($statusEntity);
        $em->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Statut mis à jour avec succès.',
            'payment' => $this->mapPayment($payment),
            'statusUi' => $this->getStatusUi($statusCode),
        ]);
    }

    private function isXmlHttpRequest(Request $request): bool
    {
        return $request->isXmlHttpRequest()
            || $request->headers->get('X-Requested-With') === 'XMLHttpRequest';
    }

    private function mapPayment(Payment $payment): array
    {
        $status = strtoupper($payment->getSatusPayment()?->getStatusPayment() ?? 'INCONNU');

        return [
            'id' => $payment->getId(),
            'amount' => (float) ($payment->getAmount() ?? 0),
            'currency' => $payment->getCurrency() ?: 'XAF',
            'provider' => $payment->getProvider()?->getProvider() ?: '—',
            'status' => $status,
            'statusDescription' => self::PAYMENT_STATUSES[$status] ?? $status,
            'userEmail' => $payment->getUser()?->getEmail() ?: '—',
            'plan' => $payment->getSubscription()?->getPlan()?->getPlan() ?: '—',
            'subscriptionId' => $payment->getSubscription()?->getId(),
            'paidAt' => $payment->getPaidAt()?->format('d/m/Y H:i'),
        ];
    }

    private function getStatusUi(string $code): array
    {
        return match ($code) {
            'INITIE' => ['label' => 'INITIÉ', 'classes' => 'bg-slate-100 text-slate-700 ring-slate-200'],
            'EN_ATTENTE' => ['label' => 'EN ATTENTE', 'classes' => 'bg-amber-100 text-amber-700 ring-amber-200'],
            'SUCCES' => ['label' => 'SUCCÈS', 'classes' => 'bg-emerald-100 text-emerald-700 ring-emerald-200'],
            'ECHEC' => ['label' => 'ÉCHEC', 'classes' => 'bg-rose-100 text-rose-700 ring-rose-200'],
            'ANNULE' => ['label' => 'ANNULÉ', 'classes' => 'bg-orange-100 text-orange-700 ring-orange-200'],
            'EXPIRE' => ['label' => 'EXPIRÉ', 'classes' => 'bg-yellow-100 text-yellow-700 ring-yellow-200'],
            'REMBOURSE' => ['label' => 'REMBOURSÉ', 'classes' => 'bg-cyan-100 text-cyan-700 ring-cyan-200'],
            'PARTIEL' => ['label' => 'PARTIEL', 'classes' => 'bg-violet-100 text-violet-700 ring-violet-200'],
            'EN_VERIFICATION' => ['label' => 'EN VÉRIFICATION', 'classes' => 'bg-blue-100 text-blue-700 ring-blue-200'],
            'BLOQUE' => ['label' => 'BLOQUÉ', 'classes' => 'bg-red-100 text-red-700 ring-red-200'],
            default => ['label' => $code, 'classes' => 'bg-slate-100 text-slate-700 ring-slate-200'],
        };
    }
}