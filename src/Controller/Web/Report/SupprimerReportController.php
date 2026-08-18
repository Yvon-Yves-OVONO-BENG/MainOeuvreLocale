<?php

namespace App\Controller\Web\Report;

use App\Entity\Report;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class SupprimerReportController extends AbstractController
{
    #[Route('/reports/{id}/delete', name: 'report_soft_delete', methods: ['POST'])]
    public function __invoke(
        Report $report,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $me = $this->getUser();
        if (!$me instanceof User || $report->getReporter()?->getId() !== $me->getId()) {
            throw $this->createAccessDeniedException();
        }

        if (!$this->isCsrfTokenValid('delete_report_'.$report->getId(), (string)$request->request->get('_token'))) {
            $this->addFlash('danger', 'Action refusée. Veuillez réessayer.');
            return $this->redirectToRoute('report_my_list');
        }

        $report->setSupprimer(true); // ✅ suppression logique
        $em->flush();

        $this->addFlash('success', 'Signalement supprimé (suppression logique).');
        return $this->redirectToRoute('report_my_list');
    }
}