<?php

namespace App\Controller\Web\Talent;

use App\Repository\ApplicationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class TalentApplicationsController extends AbstractController
{
    #[IsGranted('ROLE_USER')]
    #[Route('/talent/candidatures', name: 'talent_applications', methods: ['GET'])]
    public function index(Request $request, ApplicationRepository $applicationRepository): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté.');
        }

        // Filtre status (ALL / ACCEPTED / REJECTED)
        $status = strtoupper((string) $request->query->get('status', 'ALL'));
        $allowed = ['ALL', 'ACCEPTED', 'REJECTED'];
        if (!in_array($status, $allowed, true)) {
            $status = 'ALL';
        }

        // 1) On récupère toutes les candidatures du talent (via ton repository)
        $applications = $applicationRepository->findByTalent($user);

        // 2) Filtrer en PHP (simple et fiable)
        //    ⚠️ Adapte les valeurs si chez toi c’est "ACCEPTED/REJECTED" ou "ACCEPTE/REFUSE"
        $filtered = array_values(array_filter($applications, function ($a) use ($status) {
            if ($status === 'ALL') return true;
            return strtoupper((string) $a->getStatus()) === $status;
        }));

        // 3) Stats pour l’UI (badges en haut)
        $total = count($applications);
        $acceptedCount = 0;
        $rejectedCount = 0;

        foreach ($applications as $a) {
            $s = strtoupper((string) $a->getStatus());
            if ($s === 'ACCEPTED') $acceptedCount++;
            if ($s === 'REJECTED') $rejectedCount++;
        }

        return $this->render('talent_applications/talent_applications.html.twig', [
            'applications'     => $filtered,
            'statusFilter'     => $status,
            'applicationsCount'=> $total,
            'acceptedCount'    => $acceptedCount,
            'rejectedCount'    => $rejectedCount,
        ]);
    }
}