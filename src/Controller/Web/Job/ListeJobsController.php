<?php

namespace App\Controller\Web\Job;

use App\Repository\CategorieRepository;
use App\Repository\JobRepository;
use App\Repository\ProfessionRepository;
use App\Repository\TypeJobRepository;
use App\Service\Search\SearchInputSanitizer;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ListeJobsController extends AbstractController
{
    #[Route('/liste-jobs', name: 'liste_jobs', methods: ['GET'])]
    public function index(
        Request $request,
        JobRepository $jobRepository,
        ProfessionRepository $professionRepository,
        TypeJobRepository $typeJobRepository,
        CategorieRepository $categorieRepository,
        SearchInputSanitizer $searchInputSanitizer,
    ): Response {
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
        $limit = 12;
    
        $paginator = $jobRepository->searchPaginated($filters, $page, $limit);
        
        $total = $paginator->count();
        $pages = (int) max(1, (int) ceil($total / $limit));
    
        if ($request->isXmlHttpRequest() || $request->query->getBoolean('partial')) {
            return $this->render('job/_jobs_results.html.twig', [
                'filters' => $filters,
                'jobs' => $paginator,
                'total' => $total,
                'page' => $page,
                'pages' => $pages,
                'limit' => $limit,
                'routeName' => 'liste_jobs',
                'routeParams' => [],
            ]);
        }
    
        $publishers = $jobRepository->findPublishersForFilters();
        $jobTypes   = $typeJobRepository->findBy([], ['typeJob' => 'ASC']);
        
        return $this->render('job/liste_jobs.html.twig', [
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
            'routeName' => 'liste_jobs',
            'routeParams' => [],
        ]);
    }


    #[Route('/job-categorie/{slug}', name: 'jobs_categorie', methods: ['GET'])]
    public function byCategorie(
        string $slug,
        Request $request,
        CategorieRepository $categorieRepository,
        JobRepository $jobRepository,
        ProfessionRepository $professionRepository,
        TypeJobRepository $typeJobRepository,
        SearchInputSanitizer $searchInputSanitizer,
    ): Response {
        $cat = $categorieRepository->findOneBy(['slug' => $slug, 'isActive' => true]);
        if (!$cat) {
            throw $this->createNotFoundException("Catégorie introuvable");
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
            'q'             => $keyword,
            'city'          => $city,
            'profession'    => $request->query->get('profession'),
            'categories'    => array_map('intval', $request->query->all('categories')),
            'job_types'     => array_map('intval', $request->query->all('job_types')),
            'posted_by'     => array_map('intval', $request->query->all('posted_by')),
            'salary_min'    => $request->query->getInt('salary_min', 0),
            'salary_max'    => $request->query->getInt('salary_max', 0),
            'sort'          => (string) $request->query->get('sort', 'newest'),
            'categorie'     => $slug,
        ];

        $page  = max(1, (int) $request->query->get('page', 1));
        $limit = 12;

        $paginator = $jobRepository->searchPaginated($filters, $page, $limit);
        $total = $paginator->count();
        $pages = (int) max(1, (int) ceil($total / $limit));

        if ($request->isXmlHttpRequest() || $request->query->getBoolean('partial')) {
            return $this->render('job/_jobs_results.html.twig', [
                'filters' => $filters,
                'jobs' => $paginator,
                'total' => $total,
                'page' => $page,
                'pages' => $pages,
                'limit' => $limit,
                'routeName'   => 'jobs_categorie',
                'routeParams' => ['slug' => $slug],
            ]);
        }

        $publishers = $jobRepository->findPublishersForFilters();
        $jobTypes   = $typeJobRepository->findBy([], ['typeJob' => 'ASC']);

        return $this->render('job/liste_jobs.html.twig', [
            'filters' => $filters,
            'jobs' => $paginator,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'limit' => $limit,
            'professionsWithCounts' => $professionRepository->findAllWithJobCounts(),
            'jobTypes' => $jobTypes,
            'topJobsByProfession' => $jobRepository->topJobsByProfession(2),
            'publishers' => $publishers,
            'routeName'   => 'jobs_categorie',
            'routeParams' => ['slug' => $slug],
            'categorie'   => $cat,
        ]);
    }
}
