<?php

namespace App\Controller\Web\Job;

use App\Repository\ApplicationRepository;
use App\Repository\FavoriJobRepository;
use App\Repository\JobRepository;
use App\Repository\JobViewRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class JobDetailController extends AbstractController
{
    #[Route('/jobs/{slug}', name: 'job_detail', methods: ['GET'], requirements: ['slug' => '[a-f0-9]{64}'])]
    public function show(string $slug, JobRepository $jobRepository, 
        JobViewRepository $jobViewRepository, FavoriJobRepository $favoriJobRepository, ApplicationRepository $applicationRepository ): Response
    {
        $job = $jobRepository->findOneBySlug($slug);

        if (!$job) {
            throw $this->createNotFoundException('Job introuvable.');
        }

        $relatedJobs = $jobRepository->findRelatedJobs($job, 8);

        $user = $this->getUser();
        if ($user) {
            $jobViewRepository->addUniqueView($user, $job);
        }

        $viewsCount = $jobViewRepository->countViews($job);
        $favsCount  = $favoriJobRepository->countFavorites($job);
        $isFav      = $user ? $favoriJobRepository->isFavorited($user, $job) : false;

        $hasApplied = false;
        if ($this->getUser()) {
            $hasApplied = (bool) $applicationRepository->findOneBy(['user' => $this->getUser(), 'job' => $job]);
        }

        return $this->render('job/detail.html.twig', [
            'job' => $job,
            'relatedJobs' => $relatedJobs,
            'hasApplied' => $hasApplied,
            'viewsCount' => $viewsCount,
            'favsCount' => $favsCount,
            'isFav' => $isFav,
        ]);
    }
}
