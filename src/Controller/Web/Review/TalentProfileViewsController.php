<?php

namespace App\Controller\Web\Review;

use App\Entity\User;
use App\Repository\ProfessionalProfileRepository;
use App\Repository\ViewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_TALENT')]
class TalentProfileViewsController extends AbstractController
{
    #[Route('/talent/visiteurs-profil', name: 'talent_profile_views', methods: ['GET'])]
    public function views(
    ViewRepository $viewRepo,
    ProfessionalProfileRepository $ppRepo
    ): Response {
        /**
         * @var User
         */
        $user = $this->getUser();

        $pp = $ppRepo->findOneBy(['user' => $user]); // adapte si ta relation est différente
        if (!$pp) {
            throw $this->createNotFoundException("Profil professionnel introuvable.");
        }

        $total = $viewRepo->countViews($pp);
        $views = $viewRepo->findLatestVisitors($pp, 40);

        $privacyEnabled = $user->isProfileViewsPrivate();

        return $this->render('profil_talent/views.html.twig', [
            'totalViews' => $total,
            'views' => $views,
            'privacyEnabled' => $privacyEnabled,
        ]);
    }

    #[Route('/talent/confidentialite-visiteurs', name: 'talent_profile_views_privacy', methods: ['GET','POST'])]
    public function privacy(Request $request, EntityManagerInterface $em): Response
    {
        /**
         * @var User
         */
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté.');
        }

        // Valeur actuelle (si le champ existe)
        $enabled = method_exists($user, 'isProfileViewsPrivate') ? (bool) $user->isProfileViewsPrivate() : false;

        if ($request->isMethod('POST')) {
            $enabled = $request->request->get('privacy', '0') === '1';

            // Sauvegarde (adapte aux noms réels)
            if (method_exists($user, 'setProfileViewsPrivate')) {
                $user->setProfileViewsPrivate($enabled);
            }

            $em->persist($user);
            $em->flush();

            $this->addFlash('success', $enabled
                ? 'Confidentialité activée : vous masquez la liste des visiteurs.'
                : 'Confidentialité désactivée : la liste des visiteurs est visible.'
            );

            return $this->redirectToRoute('talent_profile_views_privacy');
        }

        return $this->render('profil_talent/privacy.html.twig', [
            'enabled' => $enabled,
        ]);
    }
}