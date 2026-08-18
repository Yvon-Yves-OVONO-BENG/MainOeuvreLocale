<?php

namespace App\Controller\Web;

use App\Entity\User;
use App\Repository\JobRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class JobGeoController extends AbstractController
{
    #[Route('/jobs/proches-de-moi', name: 'jobs_near_me', methods: ['GET'])]
    public function jobsNearMe(Request $request, JobRepository $jobRepository): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $profile = method_exists($user, 'getPersonalProfile') ? $user->getPersonalProfile() : null;
        $city = $profile && method_exists($profile, 'getCity') ? trim((string) $profile->getCity()) : '';

        if ($city === '') {
            $this->addFlash('warning', 'Veuillez compléter votre ville pour voir les offres proches.');
            return $this->redirectToRoute('profile_edit');
        }

        $page = max(1, $request->query->getInt('page', 1));
        $limit = 12;

        $total = $jobRepository->countActiveByCity($city);
        $pages = max(1, (int) ceil($total / $limit));

        if ($page > $pages) {
            $page = $pages;
        }

        $jobs = $jobRepository->findActiveByCityPaginated($city, $page, $limit);

        return $this->render('job/jobs_near_me.html.twig', [
            'jobs' => $jobs,
            'city' => $city,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => $pages,
        ]);
    }

    #[Route('/jobs/carte', name: 'jobs_map', methods: ['GET'])]
    public function jobsMap(JobRepository $jobRepository): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $profile = method_exists($user, 'getPersonalProfile') ? $user->getPersonalProfile() : null;
        $city = $profile && method_exists($profile, 'getCity') ? trim((string) $profile->getCity()) : '';

        if ($city === '') {
            $this->addFlash('warning', 'Veuillez compléter votre ville pour afficher la carte.');
            return $this->redirectToRoute('profile_edit');
        }

        $jobs = $jobRepository->findActiveByCity($city);

        return $this->render('job/jobs_map.html.twig', [
            'jobs' => $jobs,
            'city' => $city,
            'total' => count($jobs),
        ]);
    }
}