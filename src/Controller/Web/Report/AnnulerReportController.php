<?php

namespace App\Controller\Web\Report;

use App\Entity\Report;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AnnulerReportController extends AbstractController
{
    #[Route('/reports/{id}/cancel', name: 'report_cancel', methods: ['POST'])]
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

        if (!$this->isCsrfTokenValid('cancel_report_'.$report->getId(), (string)$request->request->get('_token'))) {
            $this->addFlash('danger', 'Action refusée. Veuillez réessayer.');
            return $this->redirectToRoute('report_my_list');
        }

        if ($report->getStatus() === Report::STATUS_OPEN) {
            $report->cancel();
            $em->flush();
            $this->addFlash('success', 'Merci, votre signalement a été annulé.');
        } else {
            $this->addFlash('info', 'Ce signalement ne peut plus être annulé.');
        }

        return $this->redirectToRoute('report_my_list');
    }
}