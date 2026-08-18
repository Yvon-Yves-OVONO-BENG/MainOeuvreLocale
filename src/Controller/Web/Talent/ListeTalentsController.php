<?php

namespace App\Controller\Web\Talent;

use App\Entity\User;
use App\Repository\ProfessionalProfileRepository;
use App\Repository\RatingRepository;
use App\Repository\ReviewRepository;
use App\Repository\UserRepository;
use App\Service\Search\SearchInputSanitizer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ListeTalentsController extends AbstractController
{
    #[Route('/liste-talents', name: 'liste_talents')]
    public function listeTalents(
        Request $request,
        ProfessionalProfileRepository $repo,
        RatingRepository $ratingRepo,
        ReviewRepository $reviewRepository,
        SearchInputSanitizer $searchInputSanitizer
    ): Response {
        try {
            $q = $searchInputSanitizer->sanitizeKeyword($request->query->get('q', ''));
        } catch (\InvalidArgumentException $exception) {
            $q = '';
            $this->addFlash('warning', $exception->getMessage());
        }

        $sort = (string) $request->query->get('sort', 'new');
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 10;

        $me = $this->getUser();
        if (!$me instanceof User) {
            $me = null;
        }

        $p = $repo->paginateTalents($me, $q, $sort, $page, $limit);
        $talents = $p['items'];

        $userIds = [];
        foreach ($talents as $prof) {
            $u = $prof->getUser();
            if ($u) {
                $userIds[] = (int) $u->getId();
            }
        }
        $userIds = array_values(array_unique($userIds));

        $statsMap = $userIds ? $reviewRepository->getStatsForUsers($userIds) : [];

        return $this->render('liste_talents/liste_talents.html.twig', [
            'talents'      => $talents,
            'pagination'   => $p,
            'ratingsStats' => $statsMap,
            'q'            => $q,
            'sort'         => $sort,
            'listingType'  => 'talent',
        ]);
    }

    #[Route(
        '/review/user/{slug}',
        name: 'ajax_user_reviews',
        methods: ['GET'],
        requirements: ['slug' => '[a-f0-9]{64}']
    )]
    #[IsGranted('ROLE_USER')]
    public function userReviews(
        string $slug,
        Request $request,
        UserRepository $userRepository,
        ReviewRepository $reviewRepository
    ): JsonResponse {
        if (!$this->consumeReviewReadQuota($request)) {
            $response = $this->json([
                'error' => 'Trop de requêtes. Veuillez patienter avant de réessayer.',
            ], Response::HTTP_TOO_MANY_REQUESTS);
            $response->headers->set('Retry-After', '60');
            $response->headers->set('Cache-Control', 'no-store, private');

            return $response;
        }

        $user = $userRepository->findOneBy(['slug' => $slug]);
        if (!$user instanceof User) {
            throw $this->createNotFoundException('Utilisateur introuvable.');
        }

        $reviews = $reviewRepository->findLastReviews($user);
        $data = [];

        foreach ($reviews as $review) {
            $criteria = [];
            foreach ($review->getScores() as $score) {
                $criteria[] = [
                    'name'  => $score->getCriteria()->getName(),
                    'score' => $score->getScore(),
                ];
            }

            $data[] = [
                'author'   => $review->getAuthor()?->getPublicDisplayName() ?? 'Anonyme',
                'comment'  => $review->getComment(),
                'date'     => $review->getCreatedAt()?->format('d/m/Y H:i'),
                'criteria' => $criteria,
            ];
        }

        $response = $this->json($data);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Pragma', 'no-cache');

        return $response;
    }

    /**
     * Limitation simple et sans dépendance supplémentaire : 30 lectures/minute
     * par session + adresse IP. L'endpoint est déjà réservé aux comptes connectés.
     */
    private function consumeReviewReadQuota(Request $request): bool
    {
        $session = $request->getSession();
        $key = '_mol_review_rate_' . sha1((string) $request->getClientIp());
        $now = time();
        $state = $session->get($key, ['start' => $now, 'count' => 0]);

        if (!is_array($state) || ($now - (int) ($state['start'] ?? 0)) >= 60) {
            $state = ['start' => $now, 'count' => 0];
        }

        $state['count'] = (int) ($state['count'] ?? 0) + 1;
        $session->set($key, $state);

        return $state['count'] <= 30;
    }
}
