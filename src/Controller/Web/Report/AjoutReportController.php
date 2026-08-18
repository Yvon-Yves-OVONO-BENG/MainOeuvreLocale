<?php

namespace App\Controller\Web\Report;

use App\Entity\Report;
use App\Entity\User;
use App\Form\ReportType;
use App\Repository\ReportRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AjoutReportController extends AbstractController
{
    #[Route('/reports/user/{id}/new', name: 'report_user_create', methods: ['GET', 'POST'])]
    public function __invoke(
        User $target,
        Request $request,
        EntityManagerInterface $em,
        ReportRepository $reportRepo
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        $me = $this->getUser();
        if (!$me instanceof User) {
            throw $this->createAccessDeniedException();
        }
        if ($target->getId() === $me->getId()) {
            $this->addFlash('warning', 'Vous ne pouvez pas signaler votre propre compte.');
            return $this->redirectToRoute('accueil');
        }

        // éviter doublon OPEN (optionnel)
        $already = $reportRepo->findOneBy([
            'reporter' => $me,
            'targetUser' => $target,
            'status' => Report::STATUS_OPEN,
            'supprimer' => false,
        ]);
        if ($already) {
            $this->addFlash('info', 'Vous avez déjà un signalement actif pour ce compte.');
            return $this->redirectToRoute('report_my_list');
        }

        $report = new Report();
        $report->setReporter($me);
        $report->setSlug(\App\Util\HashedSlugGenerator::generate());
        $report->setTargetUser($target);

        $form = $this->createForm(ReportType::class, $report);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) 
        {
            $em->persist($report);
            $em->flush();

            $this->addFlash('success', 'Signalement enregistré. Merci pour votre vigilance.');
            return $this->redirectToRoute('report_my_list');
        }

        return $this->render('report/new.html.twig', [
            'form' => $form,
            'target' => $target,
        ]);
    }
}
