<?php
// src/Controller/Admin/CategorieController.php
namespace App\Controller\Web\Admin;

use App\Entity\Categorie;
use App\Form\CategorieType;
use App\Repository\CategorieRepository;
use App\Service\Search\SearchInputSanitizer;
use App\Util\HashedSlugGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/categories')]
class CategorieController extends AbstractController
{
    #[Route('/', name: 'admin_categories_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('admin/categorie/index.html.twig');
    }

    #[Route('/data', name: 'admin_categories_data', methods: ['GET'])]
    public function getData(Request $request, CategorieRepository $repository): JsonResponse
    {
        $search = trim((string) $request->query->get('search', ''));
        $page = max(1, $request->query->getInt('page', 1));
        // La page des professions demande toutes les catégories pour le select.
        // La limite reste bornée afin d'éviter une requête sans contrôle.
        $limit = min(1000, max(1, $request->query->getInt('limit', 10)));
        
        $categories = $repository->findBySearch($search);
        $total = count($categories);
        $totalPages = $total > 0 ? (int) ceil($total / $limit) : 0;
        
        $offset = ($page - 1) * $limit;
        $categoriesSlice = array_slice($categories, $offset, $limit);
        
        $data = array_map(function($categorie) {
            return [
                'id' => $categorie->getSlug(),
                'nom' => $categorie->getNom(),
                'description' => $categorie->getDescription(),
                'professions_count' => count($categorie->getProfessions()),
            ];
        }, $categoriesSlice);
        
        return new JsonResponse([
            'data' => $data,
            'total' => $total,
            'totalPages' => $totalPages,
            'currentPage' => $page,
            'limit' => $limit,
        ]);
    }

    #[Route('/new', name: 'admin_categories_new', methods: ['POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        CategorieRepository $repository,
        SearchInputSanitizer $searchInputSanitizer,
    ): JsonResponse
    {
        $categorie = new Categorie();
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['success' => false, 'message' => 'Données JSON invalides.'], 400);
        }

        try {
            $nom = $searchInputSanitizer->sanitizeKeyword($data['nom'] ?? '', 255);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }

        if ($repository->findDuplicateByName($nom)) {
            return $this->json(['success' => false, 'message' => 'Cette catégorie existe déjà.'], 409);
        }
        
        $categorie->setNom($nom);
        $categorie->setDescription(trim((string) ($data['description'] ?? '')));
        $categorie->setSlug(HashedSlugGenerator::generate());
        $categorie->setIsActive(true);
        $categorie->setCreatedAt(new \DateTimeImmutable());
        
        $em->persist($categorie);
        $em->flush();
        
        return new JsonResponse(['success' => true, 'message' => 'Catégorie créée avec succès']);
    }

    #[Route('/{id}', name: 'admin_categories_show', methods: ['GET'])]
    public function show(
        #[MapEntity(mapping: ['id' => 'slug'])]
        Categorie $categorie,
    ): JsonResponse
    {
        return new JsonResponse([
            'id' => $categorie->getSlug(),
            'nom' => $categorie->getNom(),
            'description' => $categorie->getDescription(),
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_categories_edit', methods: ['PUT', 'POST'])]
    public function edit(
        Request $request,
        #[MapEntity(mapping: ['id' => 'slug'])]
        Categorie $categorie,
        EntityManagerInterface $em,
        CategorieRepository $repository,
        SearchInputSanitizer $searchInputSanitizer,
    ): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['success' => false, 'message' => 'Données JSON invalides.'], 400);
        }

        try {
            $nom = $searchInputSanitizer->sanitizeKeyword($data['nom'] ?? '', 255);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }

        if ($repository->findDuplicateByName($nom, $categorie->getId())) {
            return $this->json(['success' => false, 'message' => 'Cette catégorie existe déjà.'], 409);
        }
        
        $categorie->setNom($nom);
        $categorie->setDescription(trim((string) ($data['description'] ?? '')));
        
        $em->flush();
        
        return new JsonResponse(['success' => true, 'message' => 'Catégorie modifiée avec succès']);
    }

    #[Route('/{id}', name: 'admin_categories_delete', methods: ['DELETE'])]
    public function delete(
        #[MapEntity(mapping: ['id' => 'slug'])]
        Categorie $categorie,
        EntityManagerInterface $em,
    ): JsonResponse
    {
        $em->remove($categorie);
        $em->flush();
        
        return new JsonResponse(['success' => true, 'message' => 'Catégorie supprimée avec succès']);
    }

    #[Route('/batch-delete', name: 'admin_categories_batch_delete', methods: ['POST'])]
    public function batchDelete(Request $request, CategorieRepository $repository): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $ids = $data['ids'] ?? [];
        
        if (empty($ids)) {
            return new JsonResponse(['success' => false, 'message' => 'Aucune catégorie sélectionnée'], 400);
        }
        
        $repository->deleteMultiple($ids);
        
        return new JsonResponse(['success' => true, 'message' => 'Catégories supprimées avec succès']);
    }
}
