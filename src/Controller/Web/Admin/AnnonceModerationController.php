<?php

namespace App\Controller\Web\Admin;

use App\Entity\Annonce;
use App\Entity\User;
use App\Form\AnnonceType;
use App\Repository\AnnonceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/annonces')]
#[IsGranted(new Expression('is_granted("ROLE_MODERATEUR") or is_granted("ROLE_ADMIN") or is_granted("ROLE_SUPER_ADMIN")'))]
final class AnnonceModerationController extends AbstractController
{
    #[Route('/moderation', name: 'admin_annonces_moderation', methods: ['GET'])]
    public function index(AnnonceRepository $annonceRepository): Response
    {
        return $this->render('admin/annonce/moderation.html.twig', [
            'annonces' => $annonceRepository->findForModeration(),
        ]);
    }

    #[Route('/{slug}/modifier', name: 'admin_annonce_edit', methods: ['GET', 'POST'], requirements: ['slug' => '[a-f0-9]{64}'])]
    public function edit(
        #[MapEntity(mapping: ['slug' => 'slug'])]
        Annonce $annonce,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $wasPublished = $annonce->isPublier();
        $form = $this->createForm(AnnonceType::class, $annonce, [
            'action' => $this->generateUrl('admin_annonce_edit', ['slug' => $annonce->getSlug()]),
            'method' => 'POST',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($annonce->getProfession()?->getCategorie()?->getId() !== $annonce->getCategorie()?->getId()) {
                $this->addFlash('toast_error', 'La profession sélectionnée ne correspond pas à la catégorie.');
            } else {
                if (!$annonce->isAfficherMaintenant()) {
                    $annonce
                        ->setPublier(false)
                        ->setPubliePar(null)
                        ->setDatePublication(null);
                } elseif ($wasPublished) {
                    $actor = $this->getUser();
                    $annonce->setPublier(true);

                    if ($actor instanceof User) {
                        $annonce->setPubliePar($actor);
                    }
                }

                $entityManager->flush();
                $this->addFlash('toast_success', 'Annonce modifiée avec succès par l’équipe de modération.');

                return $this->redirectToRoute('admin_annonces_moderation');
            }
        }

        return $this->render('admin/annonce/edit.html.twig', [
            'annonce' => $annonce,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{slug}/publication', name: 'admin_annonce_toggle_publish', methods: ['POST'], requirements: ['slug' => '[a-f0-9]{64}'])]
    public function togglePublish(
        #[MapEntity(mapping: ['slug' => 'slug'])]
        Annonce $annonce,
        Request $request,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        if (!$this->isCsrfTokenValid('annonce_publish_' . $annonce->getId(), (string) $request->request->get('_token'))) {
            return $this->json(['success' => false, 'message' => 'Jeton de sécurité invalide. Actualisez la page.'], 419);
        }

        $publish = filter_var($request->request->get('enabled'), FILTER_VALIDATE_BOOL);
        if ($publish && !$annonce->isAfficherMaintenant()) {
            return $this->json([
                'success' => false,
                'message' => 'L’utilisateur n’a pas demandé l’affichage de cette annonce.',
            ], 422);
        }

        $actor = $this->getUser();
        if (!$actor instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $annonce->setPublier($publish);
        if ($publish) {
            $annonce
                ->setPubliePar($actor)
                ->setDatePublication(new \DateTimeImmutable());
        } else {
            $annonce
                ->setPubliePar(null)
                ->setDatePublication(null);
        }

        $entityManager->flush();

        $payload = [
            'success' => true,
            'published' => $publish,
            'status' => $publish ? 'published' : 'pending',
            'datePublication' => $annonce->getDatePublication()?->format('d/m/Y H:i'),
            'message' => $publish
                ? 'Annonce publiée avec succès.'
                : 'Annonce retirée de la publication et replacée en attente.',
        ];

        if ($this->isGranted('ROLE_ADMIN') || $this->isGranted('ROLE_SUPER_ADMIN')) {
            $payload['publiePar'] = $publish ? $actor->getEmail() : null;
        }

        return $this->json($payload);
    }
}
