<?php

namespace App\Controller\Web\Job;

use App\Entity\User;
use App\Repository\JobRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PublishedJobsController extends AbstractController
{
    #[Route('/talent/publisher/jobs', name: 'talent_publisher_jobs', methods: ['GET'])]
    public function __invoke(JobRepository $jobRepository): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $me = $this->getUser();
        if (!$me instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $rows = $jobRepository->findPublishedJobsWithApplicantCounts($me->getId(), 60);

        return $this->render('job/mes_jobs.html.twig', [
            'rows' => $rows,
        ]);
    }
}