<?php

namespace App\Controller\Web\Report;

use App\Entity\User;
use App\Repository\ReportRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MesReportsController extends AbstractController
{
    #[Route('/reports/me', name: 'report_my_list', methods: ['GET'])]
    public function __invoke(Request $request, ReportRepository $repo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $me = $this->getUser();
        if (!$me instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $page  = max(1, (int) $request->query->get('page', 1));
        $limit = 5;

        // ✅ Toute la requête + pagination est gérée dans le repository
        $result = $repo->paginateMyReports($me, $page, $limit);

        return $this->render('report/my_reports.html.twig', [
            'reports' => $result['items'],
            'pagination' => $result['pagination'],
        ]);
    }
}