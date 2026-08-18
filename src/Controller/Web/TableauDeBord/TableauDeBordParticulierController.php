<?php

namespace App\Controller\Web\TableauDeBord;

use App\Entity\Appointment;
use App\Entity\PaymentDispute;
use App\Repository\AppointmentRepository;
use App\Repository\ConversationRepository;
use App\Repository\FavoriteRepository;
use App\Repository\JobRepository;
use App\Repository\MessageRepository;
use App\Repository\PaymentDisputeRepository;
use App\Repository\SanctionRepository;
use App\Repository\ViewRepository;
use App\Service\FavoriteService;
use App\Service\ProfileCompletionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER', message: 'Accès refusé. Connectez-vous')]
class TableauDeBordParticulierController extends AbstractController
{
    #[Route('/tableau-de-bord-particulier', name: 'tableau_de_bord_particulier')]
    public function index(
        JobRepository $jobRepository,
        MessageRepository $messageRepository,
        ConversationRepository $conversationRepository,
        FavoriteRepository $favoriteRepository,
        FavoriteService $favoriteService,
        ViewRepository $viewRepository,
        SanctionRepository $sanctionRepository,
        AppointmentRepository $appointmentRepository,
        PaymentDisputeRepository $paymentDisputeRepository,
        ProfileCompletionService $completionService,
    ): Response {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $personnalProfile = $user->getPersonalProfile();

        if (!$personnalProfile) {
            return $this->redirectToRoute('profile_edit');
        }

        // ===== KPI =====
        $talentsContacted = $conversationRepository->countDistinctTalentsContacted($user);
        $projectsPublished = $jobRepository->countProjectsPublishedBy($user);
        $activeMessages    = $messageRepository->countActiveMessagesForClient($user);
        $unreadCount = $messageRepository->countUnreadForUser($user);

        // ===== TABLES =====
        $activeProjects        = $jobRepository->findActiveProjectsByUser($user, 3);
        $recentConversations    = $conversationRepository->findRecentForClient($user, 10);
        $favoriteTalents        = $favoriteRepository->findRecentFavorites($user, 8);

        // ✅ KPI Contacts
        // $talentsContacted = $conversationRepository->countContactsForUser($user);

        // ✅ Petit aperçu récent (optionnel dans dashboard)
        $recentContacts = $conversationRepository->findRecentContactsForDashboard($user, 4);

        // ✅ si tu veux aussi afficher "contacts avec non lus"
        $contactsWithUnread = $conversationRepository->countContactsWithUnread($user);

        // ✅ Favoris : combien de fois ce talent est dans les favoris
        $favoritedByCount = $favoriteService->jeSuisFavori($user);

        $favoritesCount = $favoriteService->mesFavoris($user);

        $totalViews  = $viewRepository->countTotalViewsForProfile($user->getId());

        $activeSanctionsCount = 0;
        $recentActiveSanctions = [];

        if ($user) {
            $activeSanctionsCount = $sanctionRepository->countActiveForUser($user);
            $recentActiveSanctions = $sanctionRepository->findActiveForUser($user, 3);
        }

        $openPaymentDisputesCount = 0;

        if ($user) {
            $openPaymentDisputesCount =
                $paymentDisputeRepository->countByStatusForUser($user, PaymentDispute::STATUS_OPEN)
                + $paymentDisputeRepository->countByStatusForUser($user, PaymentDispute::STATUS_REVIEW);
        }
        
        $pendingAppointmentsCount = $appointmentRepository->count([
            'talent' => $this->getUser(),
            'status' => Appointment::STATUS_PROPOSED,
        ]);

        $completion = $completionService->calculate($user);

        return $this->render('tableau_de_bord_particulier/tableau_de_bord_particulier.html.twig', [
            'unreadCount' =>$unreadCount,
            'pendingAppointmentsCount' => $pendingAppointmentsCount,
            'openPaymentDisputesCount' => $openPaymentDisputesCount,
            'activeSanctionsCount' => $activeSanctionsCount,
            'kpi' => [
                'talentsContacted' => $talentsContacted,
                'projectsPublished' => $projectsPublished,
                'activeMessages' => $activeMessages,
                'contactsWithUnread' => $contactsWithUnread,
                'favoritesCount' => $favoritesCount,
                'favoritedByCount' => $favoritedByCount,
                'totalViews' => $totalViews,
                'completion' => $completion
            ],
            'tables' => [
                'activeProjects' => $activeProjects,
                'recentConversations' => $recentConversations,
                'favoriteTalents' => $favoriteTalents,
                'hasFavorites' => !empty($favoriteTalents),
            ],
        ]);
    }
}
