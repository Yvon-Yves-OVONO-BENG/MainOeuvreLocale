<?php

namespace App\Service;

use App\Entity\Rating;
use App\Entity\Review;
use App\Entity\User;
use App\Repository\PersonalProfileRepository;
use App\Repository\RatingRepository;
use App\Repository\ReviewRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ReputationService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly RatingRepository $ratingRepository,
        private readonly ReviewRepository $reviewRepository,
        private readonly PersonalProfileRepository $personalProfileRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function getListingData(
        string $role,
        string $title,
        string $routeName,
        ?User $viewer,
        string $q = '',
        int $page = 1,
        int $limit = 12
    ): array {
        $q = trim($q);
        $page = max(1, $page);

        $totalCount = $this->userRepository->countByRoleAndQuery($role, $viewer, $q);
        $totalPages = max(1, (int) ceil($totalCount / $limit));

        $resolvedPage = ($page > $totalPages && $totalCount > 0) ? $totalPages : $page;
        $offset = ($resolvedPage - 1) * $limit;

        $rows = $this->userRepository->searchByRoleWithRatingStats($role, $q, $limit, $offset);

        $rangeFrom = $totalCount ? ($offset + 1) : 0;
        $rangeTo = min($offset + count($rows), $totalCount);

        return [
            'title' => $title,
            'rows' => $rows,
            'q' => $q,
            'currentPage' => $resolvedPage,
            'totalPages' => $totalPages,
            'totalCount' => $totalCount,
            'rangeFrom' => $rangeFrom,
            'rangeTo' => $rangeTo,
            'routeName' => $routeName,
            'redirectPage' => $resolvedPage !== $page ? $resolvedPage : null,
        ];
    }

    public function getApiListingPayload(
        string $role,
        string $title,
        string $routeName,
        ?User $viewer,
        string $q = '',
        int $page = 1,
        int $limit = 12
    ): array {
        $data = $this->getListingData($role, $title, $routeName, $viewer, $q, $page, $limit);

        $safeRows = [];
        foreach ($data['rows'] as $row) {
            $user = is_array($row) ? ($row[0] ?? null) : $row;
            if (!$user instanceof User) {
                continue;
            }

            $profile = $user->getPersonalProfile();
            $safeRows[] = [
                'publicId' => $user->getSlug(),
                'profileSlug' => $profile?->getSlug(),
                'fullName' => $user->getPublicDisplayName(),
                'photo' => $profile?->getPhoto(),
                'city' => $profile?->getCity(),
                'country' => $user->getCountry()?->getCountry(),
                'avgStars' => (float) (is_array($row) ? ($row['avgStars'] ?? 0) : 0),
                'ratingsCount' => (int) (is_array($row) ? ($row['ratingsCount'] ?? 0) : 0),
            ];
        }

        return [
            'ok' => true,
            'title' => $data['title'],
            'rows' => $safeRows,
            'q' => $data['q'],
            'currentPage' => $data['currentPage'],
            'totalPages' => $data['totalPages'],
            'totalCount' => $data['totalCount'],
            'rangeFrom' => $data['rangeFrom'],
            'rangeTo' => $data['rangeTo'],
            'routeName' => $data['routeName'],
            'redirectPage' => $data['redirectPage'],
        ];
    }

    public function getShowPageData(string $slug, ?User $me, bool $editMode = false): array
    {
        $target = $this->findTargetUserBySlugOrFail($slug);

        if ($me) {
            $this->assertNotSelf($me, $target);
        } else {
            $editMode = false;
        }

        $myRating = $me ? $this->ratingRepository->findMine($target, $me) : null;
        $myReview = $me ? $this->reviewRepository->findMine($target, $me) : null;
        $hasMyOpinion = ($myRating !== null || $myReview !== null);

        $avgStars = (float) ($this->ratingRepository->avgStarsFor($target) ?? 0);
        $ratingsCount = (int) ($this->ratingRepository->countFor($target) ?? 0);
        $reviews = $this->reviewRepository->findForTarget($target, 50);

        return [
            'target' => $target,
            'avgStars' => $avgStars,
            'ratingsCount' => $ratingsCount,
            'reviews' => $reviews,
            'isSerious' => ($avgStars >= 4.0 && $ratingsCount >= 5),
            'myRating' => $myRating,
            'myReview' => $myReview,
            'editMode' => $editMode,
            'hasMyOpinion' => $hasMyOpinion,
            'canRate' => $me !== null,
            'formData' => $this->buildFormData($myRating, $myReview, $editMode, $hasMyOpinion),
        ];
    }

    public function getApiShowPayload(string $slug, ?User $me, bool $editMode = false): array
    {
        $data = $this->getShowPageData($slug, $me, $editMode);

        return [
            'ok' => true,
            'target' => $this->formatUser($data['target']),
            'avgStars' => $data['avgStars'],
            'ratingsCount' => $data['ratingsCount'],
            'isSerious' => $data['isSerious'],
            'editMode' => $data['editMode'],
            'hasMyOpinion' => $data['hasMyOpinion'],
            'canRate' => $data['canRate'],
            'myRating' => $data['myRating'] ? [
                'stars' => $data['myRating']->getStars(),
                'createdAt' => $data['myRating']->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            ] : null,
            'myReview' => $data['myReview'] ? [
                'comment' => $data['myReview']->getComment(),
                'createdAt' => $data['myReview']->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            ] : null,
            'formData' => $data['formData'],
            'reviews' => array_map(
                fn (Review $review) => $this->formatReview($review),
                $data['reviews']
            ),
        ];
    }

    public function submitOpinion(string $slug, User $me, int $stars, string $comment): array
    {
        $target = $this->findTargetUserBySlugOrFail($slug);
        $this->assertNotSelf($me, $target);

        $myRating = $this->ratingRepository->findMine($target, $me);
        $myReview = $this->reviewRepository->findMine($target, $me);
        $hasMyOpinion = ($myRating !== null || $myReview !== null);

        $rating = $myRating ?? new Rating();
        if (!$myRating) {
            $rating->setUser($target);
            $rating->setRatingBy($me);
            $rating->setCreatedAt(new \DateTime());
        }
        $rating->setStars(max(1, min(5, $stars)));
        if (!$rating->getCreatedAt()) {
            $rating->setCreatedAt(new \DateTime());
        }
        $this->entityManager->persist($rating);

        $review = $myReview ?? new Review();
        if (!$myReview) {
            $review->setAuthor($me);
            $review->setTarget($target);
        }
        $review->setComment(trim($comment));
        $this->entityManager->persist($review);

        $this->entityManager->flush();

        return [
            'ok' => true,
            'message' => $hasMyOpinion ? 'Avis modifié ✅' : 'Avis enregistré ✅',
            'targetSlug' => $target->getPersonalProfile()?->getSlug() ?? $slug,
        ];
    }

    private function buildFormData(
        ?Rating $myRating,
        ?Review $myReview,
        bool $editMode,
        bool $hasMyOpinion
    ): array {
        if ($editMode && $hasMyOpinion) {
            return [
                'stars' => $myRating?->getStars(),
                'comment' => $myReview?->getComment() ?? '',
            ];
        }

        return [
            'stars' => null,
            'comment' => '',
        ];
    }

    private function findTargetUserBySlugOrFail(string $slug): User
    {
        $personalProfile = $this->personalProfileRepository->findOneBy(['slug' => $slug]);

        if (!$personalProfile || !$personalProfile->getUser()) {
            throw new NotFoundHttpException('Utilisateur introuvable.');
        }

        return $personalProfile->getUser();
    }

    private function assertNotSelf(User $me, User $target): void
    {
        if ($me->getId() === $target->getId()) {
            throw new BadRequestHttpException('Tu ne peux pas te noter toi-même.');
        }
    }

    private function formatUser(User $user): array
    {
        $profile = $user->getPersonalProfile();

        return [
            'publicId' => $user->getSlug(),
            'roles' => $user->getRoles(),
            'slug' => $profile?->getSlug(),
            'fullName' => $user->getPublicDisplayName(),
        ];
    }

    private function formatReview(Review $review): array
    {
        $author = $review->getAuthor();

        return [
            'id' => $review->getId(),
            'comment' => $review->getComment(),
            'createdAt' => $review->getCreatedAt()?->format(\DateTimeInterface::ATOM),
            'author' => $author ? $this->formatUser($author) : null,
        ];
    }
}
