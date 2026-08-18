<?php

namespace App\Controller\Web\TableauDeBord;

use App\Entity\User;
use App\Form\AvailabilityType;
use App\Service\TableauDeBord\TalentDashboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER', message: 'Accès refusé. Connectez-vous')]
class TableauDeBordTalentController extends AbstractController
{
    #[Route('/tableau-de-bord-talent', name: 'tableau_de_bord_talent')]
    public function index(
        Request $request,
        TalentDashboardService $dashboardService
    ): Response {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $data = $dashboardService->getDashboardData($user);

        if (($data['hasProfile'] ?? false) === false) {
            return $this->redirectToRoute($data['redirectRoute'] ?? 'profile_edit');
        }

        $availabilityForm = $this->createForm(AvailabilityType::class);
        $availabilityForm->handleRequest($request);

        if ($availabilityForm->isSubmitted() && $availabilityForm->isValid()) {
            $status = $availabilityForm->get('statusProfile')->getData();
            $dashboardService->updateAvailability($data['profile'], $status);

            $this->addFlash('success', 'Disponibilité mise à jour.');

            return $this->redirectToRoute('tableau_de_bord_talent');
        }

        $data['availabilityForm'] = $availabilityForm->createView();

        return $this->render('tableau_de_bord_talent/tableau_de_bord_talent.html.twig', $data);
    }
}