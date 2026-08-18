<?php

namespace App\Service\TableauDeBord;

use App\Entity\Application;
use App\Entity\Appointment;
use App\Entity\PaymentDispute;
use App\Entity\ProfessionalProfile;
use App\Entity\User;
use App\Repository\ApplicationRepository;
use App\Repository\AppointmentRepository;
use App\Repository\ConversationRepository;
use App\Repository\FriendshipRepository;
use App\Repository\InvoicesRepository;
use App\Repository\JobRepository;
use App\Repository\MessageRepository;
use App\Repository\PaymentDisputeRepository;
use App\Repository\PaymentRepository;
use App\Repository\ReviewRepository;
use App\Repository\SanctionRepository;
use App\Repository\ViewRepository;
use App\Service\FavoriteService;
use App\Service\ProfileCompletionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class TalentDashboardService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ViewRepository $viewRepo,
        private FriendshipRepository $friendShipRepo,
        private MessageRepository $messageRepository,
        private PaymentRepository $paymentRepo,
        private InvoicesRepository $invoicesRepo,
        private ReviewRepository $reviewRepository,
        private JobRepository $jobRepository,
        private ConversationRepository $conversationRepository,
        private ApplicationRepository $applicationRepository,
        private ProfileCompletionService $completionService,
        private SanctionRepository $sanctionRepository,
        private PaymentDisputeRepository $paymentDisputeRepository,
        private FavoriteService $favoriteService,
        private AppointmentRepository $appointmentRepository,
        private UrlGeneratorInterface $urlGenerator
    ) {
    }

    public function getDashboardData(User $user): array
    {
        $profile = $user->getProfessionalProfile();
        $profilePersonnal = $user->getPersonalProfile();

        if (!$profile instanceof ProfessionalProfile) {
            return [
                'hasProfile' => false,
                'redirectRoute' => 'profile_edit',
            ];
        }

        $uid = (int) $user->getId();
        $city = $profilePersonnal?->getCity();

        // Avis clients
        $reviews = $this->reviewRepository->findReceivedByUser($user);

        // Candidatures du talent
        $applicationsCount = $this->em->getRepository(Application::class)->count([
            'user' => $user,
        ]);

        // Vues profil
        $totalViews  = $this->viewRepo->countTotalViewsForProfile($profile->getId());
        $uniqueViews = $this->viewRepo->countUniqueViewersForProfile($profile->getId());
        $latestViews = $this->viewRepo->findLatestViewersForProfile($profile->getId(), 15);

        // Réseau / messages
        $demandeAmities = $this->friendShipRepo->findIncomingPendingUsers($user);
        $friends = $this->friendShipRepo->findFriendsWithMeta($user);
        $unreadCount = $this->messageRepository->countUnreadForUser($user);

        // Paiements
        $totalPaid = $this->paymentRepo->sumPaidByUser($user);
        $paymentsCount = $this->paymentRepo->countByUser($uid);

        $paymentsEntities = $this->paymentRepo->findLatestByUser($uid, 5);
        $invoicesEntities = $this->invoicesRepo->findLatestByUser($uid, 5);

        $payments = array_map(function ($p) {
            $statusLabel = $p->getSatusPayment()?->getStatusPayment()
                ?? $p->getSatusPayment()?->getLabel()
                ?? '';

            $status = $this->mapStatus($statusLabel);

            $providerLabel = $p->getProvider()?->getProvider()
                ?? $p->getProvider()?->getName()
                ?? '—';

            $planName = $p->getSubscription()?->getPlan()?->getPlan() ?? 'Boost / Abonnement';

            return [
                'label'    => $planName,
                'status'   => $status,
                'method'   => $providerLabel,
                'paidAt'   => $p->getPaidAt(),
                'amount'   => (int) ($p->getAmount() ?? 0),
                'invoices' => $p->getInvoices(),
            ];
        }, $paymentsEntities);

        $invoices = array_map(function ($inv) {
            $payment = $inv->getPayment();

            return [
                'id'          => $inv->getId(),
                'ref'         => $inv->getInvoiceNumber() ?: ('INV-' . $inv->getId()),
                'amount'      => $payment ? (int) ($payment->getAmount() ?? 0) : null,
                'createdAt'   => $inv->getCreatedAt(),
                'downloadUrl' => $this->urlGenerator->generate('invoice_download', [
                    'slug' => $inv->getSlug(),
                ]),
            ];
        }, $invoicesEntities);

        // Favoris / notes
        $favoritedByCount = $this->favoriteService->mesFavoris($user);
        $favoritesCount = $this->favoriteService->mesFavoris($user);

        $averageStars = $this->reviewRepository->getStatsForUser($user->getId());

        // Tables
        $tables = [
            'recentMessages'  => $this->conversationRepository->findRecentWithLastMessageForUser($user, 3),
            'recommendedJobs' => $this->jobRepository->findLatestByCity($city, 3),
            'nearJobs'        => $this->jobRepository->findLatestByCity($city),
            'reviews'         => $this->reviewRepository->findLatestReceived($user, 2),
        ];

        // Candidats sur mes jobs
        $totalApplicantsOnMyJobs = $this->applicationRepository->countApplicantsForPublisher($user->getId());
        $remainingApplicantsOnMyJobs = $this->applicationRepository->countUnviewedApplicantsForPublisher($user->getId());

        // Completion
        $completion = $this->completionService->calculate($user);

        // Sanctions
        $activeSanctionsCount = $this->sanctionRepository->countActiveForUser($user);
        $recentActiveSanctions = $this->sanctionRepository->findActiveForUser($user, 3);

        // Litiges
        $openPaymentDisputesCount =
            $this->paymentDisputeRepository->countByStatusForUser($user, PaymentDispute::STATUS_OPEN)
            + $this->paymentDisputeRepository->countByStatusForUser($user, PaymentDispute::STATUS_REVIEW);

        // Rendez-vous
        $pendingAppointmentsCount = $this->appointmentRepository->count([
            'talent' => $user,
            'status' => Appointment::STATUS_PROPOSED,
        ]);

        return [
            'hasProfile' => true,

            'profile' => $profile,
            'profilePersonnal' => $profilePersonnal,
            'reviews' => $reviews,
            'favoritesCount' => $favoritesCount,
            'favoritedByCount' => $favoritedByCount,
            'applicationsCount' => $applicationsCount,

            'kpi' => [
                'completion' => $completion,
            ],

            'totalViews' => $totalViews,
            'uniqueViews' => $uniqueViews,
            'latestViews' => $latestViews,

            'demandeAmities' => $demandeAmities,
            'friends' => $friends,
            'unreadCount' => $unreadCount,

            'totalPaid' => $totalPaid,
            'paymentsCount' => $paymentsCount,
            'payments' => $payments,
            'invoices' => $invoices,

            'avgRating' => $averageStars['avgRating'] ?? 0,
            'voters' => $averageStars['voters'] ?? 0,
            'profileCompletion' => $this->profileCompletionPercent($profile),

            'tables' => $tables,

            'totalApplicantsOnMyJobs' => $totalApplicantsOnMyJobs,
            'remainingApplicantsOnMyJobs' => $remainingApplicantsOnMyJobs,

            'activeSanctionsCount' => $activeSanctionsCount,
            'recentActiveSanctions' => $recentActiveSanctions,

            'openPaymentDisputesCount' => $openPaymentDisputesCount,
            'pendingAppointmentsCount' => $pendingAppointmentsCount,
        ];
    }

    public function updateAvailability(ProfessionalProfile $profile, mixed $status): void
    {
        if ($status && method_exists($profile, 'setStatusProfile')) {
            $profile->setStatusProfile($status);
            $this->em->flush();
        }
    }

    /**
     * Map tes statuts BD vers ce que ton Twig attend: paid/pending/failed
     */
    private function mapStatus(string $raw): string
    {
        $v = mb_strtolower(trim($raw));

        if (in_array($v, ['succes', 'success', 'paye', 'paid'], true)) {
            return 'paid';
        }

        if (in_array($v, ['pending', 'en attente', 'attente'], true)) {
            return 'pending';
        }

        if (in_array($v, ['failed', 'echec', 'échoué'], true)) {
            return 'failed';
        }

        return $v ?: '—';
    }

    private function profileCompletionPercent(ProfessionalProfile $p): int
    {
        $fields = [
            (string) $p->getBio(),
            (string) $p->getExperienceYears(),
            (string) $p->getLatitude(),
            (string) $p->getLongitude(),
        ];

        $filled = 0;
        foreach ($fields as $v) {
            if (trim($v) !== '') {
                $filled++;
            }
        }

        return (int) round(($filled / count($fields)) * 100);
    }
}