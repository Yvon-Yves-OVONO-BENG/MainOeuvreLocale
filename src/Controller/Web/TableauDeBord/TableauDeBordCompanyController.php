<?php

namespace App\Controller\Web\TableauDeBord;

use App\Entity\Application;
use App\Entity\Appointment;
use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\PaymentDispute;
use App\Entity\PersonalProfile;
use App\Repository\ApplicationRepository;
use App\Repository\AppointmentRepository;
use App\Repository\ConversationRepository;
use App\Repository\JobRepository;
use App\Repository\MessageRepository;
use App\Repository\PaymentDisputeRepository;
use App\Repository\SanctionRepository;
use App\Service\FavoriteService;
use App\Service\ProfileCompletionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER', message: 'Accès refusé. Connectez-vous')]
class TableauDeBordCompanyController extends AbstractController
{
    #[Route('/tableau-de-bord-company', name: 'tableau_de_bord_company')]
    public function index(
        JobRepository $jobRepository,
        ApplicationRepository $applicationRepository,
        ConversationRepository $conversationRepository,
        MessageRepository $messageRepository,
        ProfileCompletionService $completionService,
        AppointmentRepository $appointmentRepository,
        PaymentDisputeRepository $paymentDisputeRepository,
        SanctionRepository $sanctionRepository,
        FavoriteService $favoriteService,
        Request $request,
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        /** @var PersonalProfile|null $company */
        $company = $user?->getPersonalProfile();

        if (!$company) {
            return $this->redirectToRoute('profile_edit');
        }

        // ===== KPI =====
        $jobsPublished = $jobRepository->countPublishedByCompanyProfile($company);
        $jobsActive    = $jobRepository->countActiveByCompanyProfile($company);

        $applicationsReceived = class_exists(Application::class)
            ? $applicationRepository->countReceivedForCompanyProfile($company)
            : 0;

        $applicationsByJob = class_exists(Application::class)
            ? $applicationRepository->countByJobForCompanyProfile($company, 8)
            : [];

        $talentsContacted = class_exists(Conversation::class)
            ? $conversationRepository->countDistinctTalentsContactedByCompanyProfile($company)
            : 0;

        // $completion = $this->companyCompletionPercent($company);

        // ===== TABLES =====
        $myActiveJobs = $jobRepository->findActiveForCompanyProfile($company, 10);

        $latestApplications = class_exists(Application::class)
            ? $applicationRepository->findLatestForCompanyProfile($company, 10)
            : [];

        $recentMessages = class_exists(Message::class)
            ? $messageRepository->findRecentForCompanyProfile($company, 10)
            : [];

        // ===== CHARTS =====
        $jobsLast30d = $jobRepository->countCreatedLastDaysForCompanyProfile($company, 30);
        
        $completion = 0;
        if ($user) {
            $completion = $completionService->calculate($user);
        }

        $favoritedByCount = $favoriteService->mesFavoris($user);

        $favoritesCount = $favoriteService->mesFavoris($user);

        $pendingAppointmentsCount = $appointmentRepository->count([
            'talent' => $this->getUser(),
            'status' => Appointment::STATUS_PROPOSED,
        ]);

        $openPaymentDisputesCount = 0;

        if ($user) {
            $openPaymentDisputesCount =
                $paymentDisputeRepository->countByStatusForUser($user, PaymentDispute::STATUS_OPEN)
                + $paymentDisputeRepository->countByStatusForUser($user, PaymentDispute::STATUS_REVIEW);
        }

        $activeSanctionsCount = 0;
        $recentActiveSanctions = [];

        if ($user) {
            $activeSanctionsCount = $sanctionRepository->countActiveForUser($user);
            $recentActiveSanctions = $sanctionRepository->findActiveForUser($user, 3);
        }

        // Filtres
        $period = (string) $request->query->get('period', '30'); // 7|30|90|custom
        $jobId  = (int) $request->query->get('job', 0);
        $q      = trim((string) $request->query->get('q', ''));
        $fromQ  = (string) $request->query->get('from', '');
        $toQ    = (string) $request->query->get('to', '');

        $tz = new \DateTimeZone('Africa/Douala');
        $today = new \DateTimeImmutable('today', $tz);

        if ($period === 'custom' && $fromQ && $toQ) {
            $from = (new \DateTimeImmutable($fromQ, $tz))->setTime(0, 0, 0);
            $to   = (new \DateTimeImmutable($toQ, $tz))->setTime(23, 59, 59);
        } else {
            $days = (int) $period;
            if (!in_array($days, [7,30,90], true)) $days = 30;
            $from = $today->sub(new \DateInterval('P' . ($days - 1) . 'D'))->setTime(0, 0, 0);
            $to   = $today->setTime(23, 59, 59);
            $period = (string) $days;
        }

        $jobsForSelect = $jobRepository->findCompanyJobsForSelect($user);
        
        $selectedJob = null;
        if ($jobId > 0) {
            $selectedJob = $jobRepository->findOneBy(['id' => $jobId, 'createdBy' => $company]);
        }

        $counts  = $applicationRepository->countByStatusForCompany($user, $selectedJob, $from, $to);

        $activeJobs = $jobRepository->countActiveJobsByUser($user);

        return $this->render('tableau_de_bord_company/tableau_de_bord_company.html.twig', [
            'counts' => $counts,
            'pendingAppointmentsCount' => $pendingAppointmentsCount,
            'openPaymentDisputesCount' => $openPaymentDisputesCount,
            'activeSanctionsCount' => $activeSanctionsCount,
            'recentActiveSanctions' => $recentActiveSanctions,
            'company' => $company,
            'favoritesCount' => $favoritesCount,
            'favoritedByCount' => $favoritedByCount,
            'kpi' => [
                'activeJobs' => $activeJobs,
                'jobsPublished' => $jobsPublished,
                'applicationsReceived' => $applicationsReceived,
                'talentsContacted' => $talentsContacted,
                'completion' => $completion,
            ],
            'tables' => [
                'myActiveJobs' => $myActiveJobs,
                'latestApplications' => $latestApplications,
                'recentMessages' => $recentMessages,
                'hasApplications' => !empty($latestApplications) || $applicationsReceived > 0,
            ],
            'charts' => [
                'applicationsByJob' => $applicationsByJob,
                'jobsLast30d' => $jobsLast30d,
            ],
        ]);
    }

    /**
     * Calcule le pourcentage de complétion du profil entreprise (stocké dans PersonalProfile).
     * @param PersonalProfile $company
     * @return int
     */
    private function companyCompletionPercent(PersonalProfile $company): int
    {
        // Ici: fullName = nom entreprise, adress/city = infos entreprise
        $fields = [
            (string) $company->getFullName(),
            (string) $company->getAdress(),
            (string) $company->getCity(),
            (string) $company->getPhoto(), // logo
        ];

        $filled = 0;
        foreach ($fields as $v) {
            if (trim($v) !== '') $filled++;
        }

        $percent = (int) round(($filled / max(count($fields), 1)) * 100);
        return min(100, max(0, $percent));
    }
}
