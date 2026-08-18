<?php

namespace App\Controller\Web\Profile;

use App\Entity\View;
use App\Repository\FavoriteRepository;
use App\Repository\ViewRepository;
use App\Repository\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\PersonalProfileRepository;
use App\Repository\ProfessionalProfileRepository;
use App\Repository\RatingRepository;
use App\Repository\TalentAvailabilityRepository;
use App\Service\ProfileCompletionService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class ProfilTalentController extends AbstractController
{
    /**
     * Affiche un profil public uniquement lorsque ses données minimales sont complètes.
     */
    #[Route('/profil-talent/{slug}', name: 'profil_talent')]
    public function profilTalent(
        string $slug,
        PersonalProfileRepository $personalProfileRepository,
        ViewRepository $viewRepo,
        ReviewRepository $reviewRepo,
        RatingRepository $ratingRepo,
        EntityManagerInterface $em,
        FavoriteRepository $favoriteRepo,
        ProfessionalProfileRepository $professionalProfileRepository,
        TalentAvailabilityRepository $talentAvailabilityRepository,
        ProfileCompletionService $profileCompletionService,
    ): Response {
        $profil = $personalProfileRepository->findOneBy(['slug' => $slug]);

        if (!$profil) {
            throw $this->createNotFoundException('Profil introuvable.');
        }

        $profileOwner = $profil->getUser();
        if (!$profileOwner || !$profileCompletionService->isReadyForPublicVisibility($profileOwner)) {
            throw $this->createNotFoundException('Ce profil est incomplet et n’est pas encore public.');
        }

        /** @var \App\Entity\User|null $me */
        $me = $this->getUser();

        $targetUser = $profil->getUser();
        $professionalProfile = $targetUser?->getProfessionalProfile();

        if ($professionalProfile) {
            $this->trackProfileView(
                $em,
                $viewRepo,
                $professionalProfile,
                $me
            );
        }

        $latestReviews = [];
        $allReviews = [];
        if ($professionalProfile && $professionalProfile->getUser()) {
            $targetUser = $professionalProfile->getUser();

            $latestReviews = $reviewRepo->findLatestForProfile($targetUser, 3);
            $allReviews    = $reviewRepo->findAllForProfile($targetUser);
        }

        $averageRating = 0;
        $ratingsCount  = 0;
        
        if ($professionalProfile && $professionalProfile->getUser()) {
        
            $targetUser = $professionalProfile->getUser();
        
            $stats = $reviewRepo->getStatsForOneUser($targetUser);
            
            $averageRating = (float) ($stats['avg'] ?? 0);
            $ratingsCount  = (int) ($stats['voters'] ?? 0);
            
           
        }

        $isFavorite = false;
        if ($me && $targetUser && $me->getId() !== $targetUser->getId()) {
            $isFavorite = $favoriteRepo->isFavorite($me, $targetUser);
        }

        $statusText = $professionalProfile
            ? $professionalProfileRepository->findStatusLabelByUserId($targetUser->getId())
            : null;

        $availabilityDates = [];
        if ($professionalProfile) {
            $availabilityDates = $talentAvailabilityRepository->findDateStringsByProfile($professionalProfile);
        }

        return $this->render('profil_talent/profil_talent.html.twig', [
            'profil'            => $profil,
            'professionalProfile' => $professionalProfile,
            'latestReviews'     => $latestReviews,
            'allReviews'        => $allReviews,
            'averageRating'     => $averageRating,
            'ratingsCount'      => $ratingsCount,
            'isFavorite'        => $isFavorite,
            'statusText'        => $statusText,
            'availabilityDates' => $availabilityDates,
        ]);
    }

    private function trackProfileView(
        EntityManagerInterface $em,
        ViewRepository $viewRepo,
        \App\Entity\ProfessionalProfile $profile,
        $viewer
    ): void {
        if (!$viewer) {
            return;
        }

        if ($profile->getUser() && $profile->getUser() === $viewer) {
            return;
        }

        $today = new \DateTimeImmutable('today');

        try {
            $view = new View();
            $view->setViewer($viewer);
            $view->setProfessionalProfile($profile);
            $view->setViewDate($today);
            $view->setCreatedAt(new \DateTimeImmutable());

            $em->persist($view);
            $em->flush();
        } catch (UniqueConstraintViolationException $e) {
        }
    }
}
