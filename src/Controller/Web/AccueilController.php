<?php

namespace App\Controller\Web;

use App\Repository\AnnonceRepository;
use App\Repository\CategorieRepository;
use App\Repository\JobRepository;
use App\Repository\ProfessionRepository;
use App\Repository\RatingRepository;
use App\Repository\TypeJobRepository;
use App\Service\Search\SearchInputSanitizer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AccueilController extends AbstractController
{
    #[Route('/', name: 'accueil')]
    public function index(Request $request,
        JobRepository $jobRepository,
        AnnonceRepository $annonceRepository,
        ProfessionRepository $professionRepository,
        CategorieRepository $categorieRepository,
        TypeJobRepository $typeJobRepository,
        RatingRepository $ratingRepository,
        SearchInputSanitizer $searchInputSanitizer): Response
    {
        $homeSection = trim((string) $request->query->get('home_section', ''));
        $isPartialRequest = $request->isXmlHttpRequest() || $request->query->getBoolean('partial');

        // La pagination des annonces évite les requêtes emplois et le rendu complet de l'accueil.
        if ($isPartialRequest && $homeSection === 'annonces') {
            $annoncePagination = $annonceRepository->searchPublic(
                ['q' => '', 'city' => ''],
                max(1, $request->query->getInt('annonce_page', 1)),
                12
            );
            $annonceUserIds = array_values(array_unique(array_filter(array_map(
                static fn ($annonce): ?int => $annonce->getUser()?->getId(),
                $annoncePagination['items']
            ))));

            return $this->render('annonce/_public_section.html.twig', [
                'annonces' => $annoncePagination['items'],
                'annonceTotal' => $annoncePagination['total'],
                'annoncePage' => $annoncePagination['page'],
                'annoncePages' => $annoncePagination['pages'],
                'annonceLimit' => $annoncePagination['limit'],
                'annonceFilters' => ['q' => '', 'city' => ''],
                'ratingStats' => $ratingRepository->getStatsForTalents($annonceUserIds),
            ]);
        }

        try {
            $keyword = $searchInputSanitizer->sanitizeKeyword($request->query->get('q', ''));
        } catch (\InvalidArgumentException $exception) {
            $keyword = '';
            $this->addFlash('warning', $exception->getMessage());
        }

        try {
            $city = $searchInputSanitizer->sanitizeLocation($request->query->get('city', ''));
        } catch (\InvalidArgumentException $exception) {
            $city = '';
            $this->addFlash('warning', $exception->getMessage());
        }

        $filters = [
            // ✅ AJOUT : Filtrer uniquement les offres approuvées pour la page publique
            'is_approved' => true,
            'q'          => $keyword,
            'city'       => $city,
            
           'professions' => array_values(array_filter(
                array_map('intval', $request->query->all('professions')),
                static fn (int $id): bool => $id > 0
            )),

            'job_types' => array_values(array_filter(
                array_map('intval', $request->query->all('job_types')),
                static fn (int $id): bool => $id > 0
            )),

            'posted_by' => array_values(array_filter(
                array_map('intval', $request->query->all('posted_by')),
                static fn (int $id): bool => $id > 0
            )),

            'salary_min' => $request->query->getInt('salary_min', 0),
            'salary_max' => $request->query->getInt('salary_max', 0),
            'sort'       => (string) $request->query->get('sort', 'newest'),
        ];

        $page  = max(1, (int) $request->query->get('page', 1));
        $limit = 8;

        $paginator = $jobRepository->searchPaginated($filters, $page, $limit);
        $total = $paginator->count();
        $pages = (int) max(1, (int) ceil($total / $limit));

        if ($isPartialRequest && $homeSection === 'jobs') {
            return $this->render('includes/_content_jobs.html.twig', [
                'filters' => $filters,
                'jobs' => $paginator,
                'total' => $total,
                'page' => $page,
                'pages' => $pages,
                'limit' => $limit,
                'routeName' => 'accueil',
                'routeParams' => [],
            ]);
        }

        $annonceFilters = ['q' => '', 'city' => ''];
        $annoncePage = max(1, $request->query->getInt('annonce_page', 1));
        $annoncePagination = $annonceRepository->searchPublic(
            $annonceFilters,
            $annoncePage,
            12
        );
        $annonceUserIds = array_values(array_unique(array_filter(array_map(
            static fn ($annonce): ?int => $annonce->getUser()?->getId(),
            $annoncePagination['items']
        ))));
        $annonceRatingStats = $ratingRepository->getStatsForTalents($annonceUserIds);

        $publishers = $jobRepository->findPublishersForFilters();
        $jobTypes   = $typeJobRepository->findBy([], ['typeJob' => 'ASC']);

        $professions = $professionRepository->findUniqueNonEmptyOrdered();
        
        return $this->render('accueil/index.html.twig', [
            'professions' => $professions,
            'filters' => $filters,
            'jobs' => $paginator,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'limit' => $limit,
            'categoriesWithCounts' => $categorieRepository->findAllWithJobCounts(),
            'professionsWithCounts' => $professionRepository->findAllWithJobCounts(),
            'jobTypes' => $jobTypes,
            'topJobsByProfession' => $jobRepository->topJobsByProfession(2),
            'publishers' => $publishers,
            'routeName' => 'accueil',
            'routeParams' => [],
            'annonces' => $annoncePagination['items'],
            'annonceTotal' => $annoncePagination['total'],
            'annoncePage' => $annoncePagination['page'],
            'annoncePages' => $annoncePagination['pages'],
            'annonceLimit' => $annoncePagination['limit'],
            'annonceFilters' => $annonceFilters,
            'ratingStats' => $annonceRatingStats,
        ]);
    }
}
