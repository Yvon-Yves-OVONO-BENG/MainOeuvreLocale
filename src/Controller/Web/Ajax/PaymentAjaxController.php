<?php

namespace App\Controller\Web\Ajax;

use App\Entity\Invoices;
use App\Entity\Payment;
use App\Entity\Plan;
use App\Entity\Subscription;
use App\Entity\User;
use App\Repository\PaymentRepository;
use App\Repository\PlanRepository;
use App\Repository\ProviderRepository;
use App\Repository\StatusPaymentRepository;
use App\Repository\StatusSubscriptionRepository;
use App\Service\QrcodeService;
use BaconQrCode\Encoder\QrCode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function Symfony\Component\String\b;

#[IsGranted('ROLE_USER')]
class PaymentAjaxController extends AbstractController
{
    #[Route('/ajax/payments/init', name: 'ajax_payment_init', methods: ['POST'])]
    public function init(
        Request $request,
        EntityManagerInterface $em,
        ProviderRepository $providerRepo,
        StatusPaymentRepository $statusPayRepo,
        StatusSubscriptionRepository $statusSubRepo,
        PlanRepository $planRepo,
        QrcodeService $qrcodeService,
    ): JsonResponse {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) return $this->json(['ok' => false, 'message' => 'Non connecté.'], 401);

        // ✅ Lire JSON
        $payload = json_decode($request->getContent(), true) ?: [];

        $method = strtoupper(trim((string)($payload['method'] ?? '')));
        $phone  = trim((string)($payload['phone'] ?? ''));
        $planId = (int)($payload['planId'] ?? 0);

        if (!in_array($method, ['OM','MOMO'], true)) {
            return $this->json(['ok'=>false,'message'=>'Méthode invalide (OM/MOMO).'], 400);
        }
        if (!preg_match('/^(6|2)\d{8}$/', $phone)) {
            return $this->json(['ok'=>false,'message'=>'Numéro invalide. Exemple: 6XXXXXXXX'], 400);
        }
        if ($planId <= 0) {
            return $this->json(['ok'=>false,'message'=>'Plan manquant.'], 400);
        }

        $planPay = $planRepo->find($planId);
        
        if (!$planPay) {
            return $this->json(['ok'=>false,'message'=>'Plan introuvable.'], 404);
        }

        $provider = $providerRepo->findOneBy(['provider' => $method]) ?? $providerRepo->findOneBy(['name' => $method]);
        if (!$provider) {
            return $this->json(['ok'=>false,'message'=>'Provider introuvable (OM/MOMO).'], 500);
        }

        $paidStatus = $statusPayRepo->findOneBy(['statusPayment' => 'SUCCES'])
            ?? $statusPayRepo->findOneBy(['label' => 'SUCCESS']);
        if (!$paidStatus) {
            return $this->json(['ok'=>false,'message'=>'StatusPayment SUCCESS manquant en base.'], 500);
        }

        $activeSub = $statusSubRepo->findOneBy(['statusSubscription' => 'ACTIVE'])
            ?? $statusSubRepo->findOneBy(['label' => 'ACTIVE']);
        if (!$activeSub) {
            return $this->json(['ok'=>false,'message'=>'StatusSubscription ACTIVE manquant en base.'], 500);
        }

        $amount  = (int)$planPay->getPrice();
        $currency = 'XAF';

        $em->beginTransaction();
        try {
            // 1) Subscription
            $subscription = new Subscription();
            $subscription->setUser($user);
            $subscription->setPlan($planPay);

            $start = new \DateTime();
            $days  = (int)$planPay->getDurationDays();
            $end   = (clone $start)->modify('+' . max(1, $days) . ' days');

            $subscription->setStartAt($start);
            $subscription->setEndAt($end);
            $subscription->setStatus($activeSub);

            $em->persist($subscription);

            // 2) Payment
            $payment = new Payment();
            $payment->setUser($user);
            $payment->setSubscription($subscription);
            $payment->setProvider($provider);
            $payment->setSatusPayment($paidStatus); // typo chez toi
            $payment->setAmount($amount);
            $payment->setCurrency($currency);
            $payment->setPaidAt(new \DateTime());

            $em->persist($payment);
            
            $em->flush(); // pour obtenir payment->id

            // 3) Invoice
            $invoice = new Invoices();
            $invoice->setUser($user);
            $invoice->setPayment($payment);
            $invoice->setCreatedAt(new \DateTime());

            $invoiceNumber = 'INV-' . date('Ymd') . '-' . str_pad((string)$payment->getId(), 6, '0', STR_PAD_LEFT);
            $invoice->setInvoiceNumber($invoiceNumber);
            $invoice->setSlug(\App\Util\HashedSlugGenerator::generate());

            $message = "Facture N° {$invoiceNumber} | "
                . $user->getPersonalProfile()->getFullName()
                . " | Montant : " . number_format($payment->getAmount(), 0, '', ' ')
                . " FCFA | https://maindoeuvrelocale.com";

            $qrcodeService->qrcode($message);

            $invoice->setQrCode($qrcodeService->qrcode($message)); 

            $em->persist($invoice);
            $em->flush();

            $em->commit();

            return $this->json([
                'ok' => true,
                'message' => sprintf('%s : demande envoyée au +237 %s. Confirmez sur votre téléphone.', $method, $phone),
                'subscriptionId' => $subscription->getId(),
                'paymentId' => $payment->getId(),
                'invoiceNumber' => $invoiceNumber,
                'redirectUrl' => $this->generateUrl('tableau_de_bord'),
            ]);

        } catch (\Throwable $e) {
            $em->rollback();
            return $this->json(['ok'=>false,'message'=>'Erreur serveur: '.$e->getMessage()], 500);
        }
    }

    #[Route('/ajax/payments/{id}/status', name: 'ajax_payment_status', methods: ['GET'])]
    public function status(int $id, EntityManagerInterface $em): JsonResponse
    {
        /** @var Payment|null $payment */
        $payment = $em->getRepository(Payment::class)->find($id);
        if (!$payment) {
            return $this->json(['ok' => false, 'message' => 'Paiement introuvable.'], 404);
        }

        /**
         * @var User
         */
        $user = $this->getUser();
        if ($payment->getUser()?->getId() !== $user?->getId()) {
            return $this->json(['ok' => false, 'message' => 'Accès refusé.'], 403);
        }

        $statusEntity = $payment->getSatusPayment(); // typo dans l’entité
        $status = $statusEntity?->getStatusPayment() ?? $statusEntity?->getStatusPayment() ?? 'UNKNOWN';

        return $this->json([
            'ok' => true,
            'paymentId' => $payment->getId(),
            'status' => strtoupper((string)$status),
        ]);
    }

    #[Route('/ajax/payments/{id}/simulate', name: 'ajax_payment_simulate', methods: ['POST'])]
    public function simulate(
        int $id,
        Request $request,
        EntityManagerInterface $em,
        PaymentRepository $paymentRepo,
        StatusPaymentRepository $statusPaymentRepo,
        StatusSubscriptionRepository $statusSubRepo,
        QrcodeService $qrcodeService,
    ): JsonResponse {
        $payload = json_decode($request->getContent() ?: '[]', true) ?: [];
        $result = strtoupper(trim((string)($payload['result'] ?? 'SUCCESS'))); // SUCCESS|FAILED

        /** @var Payment|null $payment */
        $payment = $paymentRepo->find($id);
        if (!$payment) {
            return $this->json(['ok' => false, 'message' => 'Paiement introuvable.'], 404);
        }

        /** @var User $user */
        $user = $this->getUser();
        if (!$user || $payment->getUser()?->getId() !== $user->getId()) {
            return $this->json(['ok' => false, 'message' => 'Accès refusé.'], 403);
        }

        if (!in_array($result, ['SUCCESS', 'FAILED'], true)) {
            return $this->json(['ok' => false, 'message' => 'Résultat invalide (SUCCESS/FAILED).'], 400);
        }

        // Status paiement (adapte à tes champs)
        // Chez toi: findOneBy(['statusPayment' => 'EN ATTENTE']) -> donc on suppose:
        //  - EN ATTENTE
        //  - PAYÉ
        //  - ÉCHOUÉ
        $paidStatus  = $statusPaymentRepo->findOneBy(['statusPayment' => 'PAYÉ']);
        $failedStatus = $statusPaymentRepo->findOneBy(['statusPayment' => 'ÉCHOUÉ']);

        if ($result === 'FAILED') {
            if (!$failedStatus) {
                return $this->json(['ok' => false, 'message' => 'StatusPayment "ÉCHOUÉ" manquant en base.'], 500);
            }
            $payment->setSatusPayment($failedStatus);
            $em->flush();

            return $this->json([
                'ok' => true,
                'status' => 'FAILED',
                'message' => 'Paiement échoué ❌',
            ]);
        }

        // ===== SUCCESS =====
        if (!$paidStatus) {
            return $this->json(['ok' => false, 'message' => 'StatusPayment "PAYÉ" manquant en base.'], 500);
        }

        // 1) Créer subscription si pas encore liée
        $subscription = $payment->getSubscription();

        // ⚠️ IMPORTANT : comme ton init() ne lie pas Payment au Plan,
        // on récupère le plan via payload (recommandé) OU on met le plan dans Payment (si tu veux).
        // Ici: on attend planId envoyé dans simulate.
        $planId = (int)($payload['planId'] ?? 0);
        if (!$subscription) {
            if (!$planId) {
                return $this->json([
                    'ok' => false,
                    'message' => 'planId manquant pour créer la subscription (envoie-le dans simulate).'
                ], 400);
            }

            $plan = $em->getRepository(Plan::class)->find($planId);
            if (!$plan) {
                return $this->json(['ok' => false, 'message' => 'Plan introuvable (planId).'], 404);
            }

            $subscription = new Subscription();
            $subscription->setUser($user);
            $subscription->setPlan($plan);

            $start = new \DateTime();
            $days = (int)$plan->getDurationDays(); // string chez toi -> cast
            $end = (clone $start)->modify('+' . max(1, $days) . ' days');

            $subscription->setStartAt($start);
            $subscription->setEndAt($end);

            // StatusSubscription: ex "ACTIF"
            $active = $statusSubRepo->findOneBy(['statusSubscription' => 'ACTIF'])
                ?? $statusSubRepo->findOneBy(['label' => 'ACTIF']);

            if (!$active) {
                return $this->json(['ok' => false, 'message' => 'StatusSubscription "ACTIF" manquant en base.'], 500);
            }

            $subscription->setStatus($active);

            $em->persist($subscription);

            // Lier le paiement à la subscription
            $payment->setSubscription($subscription);
        }

        // 2) Mettre payment en PAYÉ
        $payment->setSatusPayment($paidStatus);
        $payment->setPaidAt(new \DateTime());

        // 3) Créer invoice
        $invoice = new Invoices();
        $invoice->setUser($user);
        $invoice->setPayment($payment);
        $invoiceNumber = 'INV-' . date('Ymd') . '-' . str_pad((string)$payment->getId(), 6, '0', STR_PAD_LEFT);
        $invoice->setInvoiceNumber($invoiceNumber);
        $invoice->setCreatedAt(new \DateTime());
        $invoice->setSlug(\App\Util\HashedSlugGenerator::generate());

        $message = "Facture N° {$invoiceNumber} | "
                . $user->getPersonalProfile()->getFullName()
                . " | Montant : " . number_format($payment->getAmount(), 0, '', ' ')
                . " FCFA | https://maindoeuvrelocale.com";

        $qrcodeService->qrcode($message);

        $invoice->setQrCode($qrcodeService->qrcode($message)); 

        $em->persist($invoice);
        $em->flush();

        return $this->json([
            'ok' => true,
            'status' => 'SUCCESS',
            'message' => 'Paiement confirmé ✅ Abonnement activé + facture générée.',
            'subscriptionId' => $subscription->getId(),
            'invoiceNumber' => $invoice->getInvoiceNumber(),
        ]);
    }


    private function readPayload(Request $request): array
    {
        $ct = (string)$request->headers->get('content-type');
        if (str_contains($ct, 'application/json')) {
            $data = json_decode((string)$request->getContent(), true);
            return is_array($data) ? $data : [];
        }
        return $request->request->all(); // form-data
    }
}
