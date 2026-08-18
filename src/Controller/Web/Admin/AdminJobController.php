<?php

namespace App\Controller\Web\Admin;

use App\Repository\JobRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/jobs', name: 'admin_jobs_')]
class AdminJobController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, JobRepository $jobRepository): Response
    {
        $status = trim((string) $request->query->get('status', ''));
        $q = trim((string) $request->query->get('q', ''));

        $page = max(1, (int) $request->query->get('page', 1));

        $perPage = (int) $request->query->get('perPage', 10);
        $allowedPerPage = [10, 20, 50, 100];

        if (!in_array($perPage, $allowedPerPage, true)) {
            $perPage = 10;
        }

        $total = $jobRepository->countAdminJobs($status, $q);
        $pages = max(1, (int) ceil($total / $perPage));

        if ($page > $pages) {
            $page = $pages;
        }

        $jobs = $jobRepository->findAdminJobsPaginated($status, $q, $page, $perPage);

        return $this->render('admin/jobs_index.html.twig', [
            'jobs' => $jobs,
            'status' => $status,
            'q' => $q,
            'page' => $page,
            'pages' => $pages,
            'perPage' => $perPage,
            'total' => $total,
        ]);
    }
}