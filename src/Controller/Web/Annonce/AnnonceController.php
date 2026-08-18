<?php

namespace App\Controller\Web\Annonce;

use App\Entity\Annonce;
use App\Entity\User;
use App\Form\AnnonceType;
use App\Repository\AnnonceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/annonces')]
#[IsGranted('ROLE_USER')]
final class AnnonceController extends AbstractController
{
    #[Route('/nouvelle', name: 'annonce_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $entityManager,
        AnnonceRepository $annonceRepository,
    ): Response {
        $user = $this->requireUser();
        $annonce = (new Annonce())->setUser($user);
        $form = $this->createForm(AnnonceType::class, $annonce);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->professionMatchesCategory($annonce)) {
                $this->addFlash('annonce_error', 'La profession sélectionnée ne correspond pas à la catégorie.');
            } else {
                $this->resetModeration($annonce);
                $entityManager->persist($annonce);
                $entityManager->flush();

                $request->getSession()->set('annonce_creation_success', [
                    'id' => $annonce->getId(),
                    'requested' => $annonce->isAfficherMaintenant(),
                ]);

                return $this->redirectToRoute('annonce_new');
            }
        }

        $creationSuccess = null;
        $successData = $request->getSession()->remove('annonce_creation_success');
        if (is_array($successData) && isset($successData['id'])) {
            $saved = $annonceRepository->find((int) $successData['id']);
            if ($saved instanceof Annonce && $saved->getUser()?->getId() === $user->getId()) {
                $creationSuccess = [
                    'annonce' => $saved,
                    'requested' => (bool) ($successData['requested'] ?? false),
                ];
            }
        }

        return $this->render('annonce/form.html.twig', [
            'form' => $form->createView(),
            'annonce' => $annonce,
            'isEdit' => false,
            'creationSuccess' => $creationSuccess,
        ]);
    }

    #[Route('/mes-annonces', name: 'annonce_manage', methods: ['GET'])]
    public function index(AnnonceRepository $annonceRepository): Response
    {
        $user = $this->requireUser();

        return $this->render('annonce/manage.html.twig', [
            'annonces' => $annonceRepository->findForUser($user),
        ]);
    }

    #[Route('/{slug}/modifier', name: 'annonce_edit', methods: ['GET', 'POST'], requirements: ['slug' => '[a-f0-9]{64}'])]
    public function edit(
        #[MapEntity(mapping: ['slug' => 'slug'])]
        Annonce $annonce,
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        $this->denyUnlessOwner($annonce);
        $form = $this->createForm(AnnonceType::class, $annonce);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$this->professionMatchesCategory($annonce)) {
                $this->addFlash('annonce_error', 'La profession sélectionnée ne correspond pas à la catégorie.');
            } else {
                $this->resetModeration($annonce);
                $entityManager->flush();
                $this->addFlash(
                    'annonce_success',
                    $annonce->isAfficherMaintenant()
                        ? 'Annonce mise à jour et renvoyée à la modération.'
                        : 'Annonce mise à jour et conservée hors affichage.'
                );

                return $this->redirectToRoute('annonce_manage');
            }
        }

        return $this->render('annonce/form.html.twig', [
            'form' => $form->createView(),
            'annonce' => $annonce,
            'isEdit' => true,
            'creationSuccess' => null,
        ]);
    }

    #[Route('/{slug}/affichage', name: 'annonce_toggle_display', methods: ['POST'], requirements: ['slug' => '[a-f0-9]{64}'])]
    public function toggleDisplay(
        #[MapEntity(mapping: ['slug' => 'slug'])]
        Annonce $annonce,
        Request $request,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $this->denyUnlessOwner($annonce);

        if (!$this->isCsrfTokenValid('annonce_display_' . $annonce->getId(), (string) $request->request->get('_token'))) {
            return $this->json(['success' => false, 'message' => 'Jeton de sécurité invalide. Actualisez la page.'], 419);
        }

        $enabled = filter_var($request->request->get('enabled'), FILTER_VALIDATE_BOOL);
        $annonce->setAfficherMaintenant($enabled);
        $this->resetModeration($annonce);
        $entityManager->flush();

        return $this->json([
            'success' => true,
            'enabled' => $enabled,
            'published' => false,
            'status' => $enabled ? 'pending' : 'draft',
            'message' => $enabled
                ? 'Votre annonce est maintenant soumise à l’appréciation d’un modérateur avant publication.'
                : 'L’annonce n’est plus affichée. Vous pourrez la soumettre de nouveau quand vous le souhaiterez.',
        ]);
    }

    #[Route('/{slug}/supprimer', name: 'annonce_delete', methods: ['POST'], requirements: ['slug' => '[a-f0-9]{64}'])]
    public function delete(
        #[MapEntity(mapping: ['slug' => 'slug'])]
        Annonce $annonce,
        Request $request,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        $this->denyUnlessOwner($annonce);

        if (!$this->isCsrfTokenValid('annonce_delete_' . $annonce->getId(), (string) $request->request->get('_token'))) {
            return $this->json(['success' => false, 'message' => 'Jeton de sécurité invalide.'], 419);
        }

        $entityManager->remove($annonce);
        $entityManager->flush();

        return $this->json(['success' => true, 'message' => 'Annonce supprimée avec succès.']);
    }

    #[Route('/suppression-multiple', name: 'annonce_bulk_delete', methods: ['POST'])]
    public function bulkDelete(
        Request $request,
        AnnonceRepository $annonceRepository,
        EntityManagerInterface $entityManager,
    ): JsonResponse {
        if (!$this->isCsrfTokenValid('annonce_bulk_delete', (string) $request->request->get('_token'))) {
            return $this->json(['success' => false, 'message' => 'Jeton de sécurité invalide.'], 419);
        }

        $slugs = array_values(array_unique(array_filter(
            array_map('strval', $request->request->all('ids')),
            static fn (string $slug): bool => preg_match('/^[a-f0-9]{64}$/', $slug) === 1
        )));

        if ($slugs === []) {
            return $this->json(['success' => false, 'message' => 'Aucune annonce sélectionnée.'], 400);
        }

        $user = $this->requireUser();
        $annonces = $annonceRepository->createQueryBuilder('a')
            ->andWhere('a.slug IN (:slugs)')
            ->andWhere('a.user = :user')
            ->setParameter('slugs', $slugs)
            ->setParameter('user', $user)
            ->getQuery()
            ->getResult();

        foreach ($annonces as $annonce) {
            $entityManager->remove($annonce);
        }
        $entityManager->flush();

        return $this->json([
            'success' => true,
            // La clé historique est conservée pour ne pas casser manage.js.
            'deletedIds' => array_map(static fn (Annonce $annonce): string => (string) $annonce->getSlug(), $annonces),
            'message' => count($annonces) . ' annonce(s) supprimée(s) avec succès.',
        ]);
    }

    private function resetModeration(Annonce $annonce): void
    {
        $annonce
            ->setPublier(false)
            ->setPubliePar(null)
            ->setDatePublication(null);
    }

    private function professionMatchesCategory(Annonce $annonce): bool
    {
        return $annonce->getProfession()?->getCategorie()?->getId() === $annonce->getCategorie()?->getId();
    }

    private function denyUnlessOwner(Annonce $annonce): void
    {
        if ($annonce->getUser()?->getId() !== $this->requireUser()->getId()) {
            throw $this->createAccessDeniedException('Vous ne pouvez gérer que vos propres annonces.');
        }
    }

    private function requireUser(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
