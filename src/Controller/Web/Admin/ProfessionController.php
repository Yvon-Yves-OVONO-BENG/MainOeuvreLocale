<?php

namespace App\Controller\Web\Admin;

use App\Entity\Profession;
use App\Repository\CategorieRepository;
use App\Repository\JobRepository;
use App\Repository\ProfessionRepository;
use App\Service\Search\SearchInputSanitizer;
use App\Service\UploadOptimizer;
use App\Util\HashedSlugGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/admin/professions')]
class ProfessionController extends AbstractController
{
    // Une taille d'entrée raisonnable est admise avant compression automatique.
    private const PHOTO_MAX_SIZE = 15 * 1024 * 1024;

    #[Route('/', name: 'admin_professions_index', methods: ['GET'])]
    public function index(CategorieRepository $categoriesRepository): Response
    {
        return $this->render('admin/profession/index.html.twig', [
            'categories' => $categoriesRepository->findAll(),
        ]);
    }

    /**
     * Liste, recherche et pagination.
     *
     * La recherche porte sur :
     * - le nom de la profession ;
     * - la description ;
     * - le nom de la catégorie.
     */
    #[Route('/data', name: 'admin_professions_data', methods: ['GET'])]
    public function getData(
        Request $request,
        EntityManagerInterface $entityManager,
        JobRepository $jobRepository
    ): JsonResponse {
        $search = trim((string) $request->query->get('search', ''));
        $page = max(1, $request->query->getInt('page', 1));
        $sortBy = (string) $request->query->get('sort', 'nom');
        $sortOrder = strtolower((string) $request->query->get('order', 'asc'));

        $sortOrder = $sortOrder === 'desc' ? 'desc' : 'asc';

        $limit = 10;
        $offset = ($page - 1) * $limit;

        $qb = $entityManager
            ->getRepository(Profession::class)
            ->createQueryBuilder('p')
            ->leftJoin('p.categorie', 'c')
            ->addSelect('c');

        /*
         * Recherche dans :
         * - p.profession
         * - p.description
         * - c.nom
         */
        if ($search !== '') {
            $normalizedSearch = mb_strtolower($search);

            $qb
                ->andWhere(
                    $qb->expr()->orX(
                        'LOWER(p.profession) LIKE :search',
                        'LOWER(COALESCE(p.description, \'\')) LIKE :search',
                        'LOWER(COALESCE(c.nom, \'\')) LIKE :search'
                    )
                )
                ->setParameter('search', '%' . $normalizedSearch . '%');
        }

        /*
         * Compter les résultats avant d'appliquer la pagination.
         */
        $countQb = clone $qb;

        $total = (int) $countQb
            ->select('COUNT(DISTINCT p.id)')
            ->getQuery()
            ->getSingleScalarResult();

        /*
         * Le tri par jobs est conservé en PHP, comme dans ton code initial,
         * car jobs_count est calculé séparément.
         */
        if ($sortBy !== 'jobs') {
            /*
             * Le tri doit être appliqué avant la pagination pour conserver
             * l'ordre alphabétique sur l'ensemble des pages.
             */
            $qb
                ->orderBy('LOWER(p.profession)', 'ASC')
                ->addOrderBy('p.id', 'ASC');
        }

        /*
         * Pour le tri par jobs, on récupère d'abord tous les résultats filtrés,
         * puis on trie et on découpe la page.
         */
        if ($sortBy === 'jobs') {
            $professions = $qb
                ->getQuery()
                ->getResult();

            $professionsWithJobs = array_map(
                function (Profession $profession) use ($jobRepository): array {
                    return [
                        'profession' => $profession,
                        'jobs_count' => $jobRepository->countByProfession(
                            $profession->getId()
                        ),
                    ];
                },
                $professions
            );

            usort(
                $professionsWithJobs,
                static function (array $a, array $b) use ($sortOrder): int {
                    if ($sortOrder === 'desc') {
                        return $b['jobs_count'] <=> $a['jobs_count'];
                    }

                    return $a['jobs_count'] <=> $b['jobs_count'];
                }
            );

            $professionsSlice = array_slice(
                $professionsWithJobs,
                $offset,
                $limit
            );
        } else {
            $professions = $qb
                ->setFirstResult($offset)
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();

            $professionsSlice = array_map(
                function (Profession $profession) use ($jobRepository): array {
                    return [
                        'profession' => $profession,
                        'jobs_count' => $jobRepository->countByProfession(
                            $profession->getId()
                        ),
                    ];
                },
                $professions
            );
        }

        $data = array_map(
            static function (array $item): array {
                /** @var Profession $profession */
                $profession = $item['profession'];

                return [
                    'id' => $profession->getSlug(),
                    'nom' => $profession->getProfession(),
                    'description' => $profession->getDescription(),
                    'photo' => $profession->getPhoto(),
                    'jobs_count' => $item['jobs_count'],
                    'categorie' => $profession->getCategorie()
                        ? [
                            'id' => $profession->getCategorie()->getSlug(),
                            'nom' => $profession->getCategorie()->getNom(),
                        ]
                        : null,
                ];
            },
            $professionsSlice
        );

        $totalPages = $total > 0
            ? (int) ceil($total / $limit)
            : 0;

        return $this->json([
            'data' => $data,
            'total' => $total,
            'totalPages' => $totalPages,
            'currentPage' => $page,
            'limit' => $limit,
        ]);
    }

    #[Route('/new', name: 'admin_professions_new', methods: ['POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        CategorieRepository $categoriesRepo,
        ProfessionRepository $professionsRepo,
        SearchInputSanitizer $searchInputSanitizer,
        SluggerInterface $slugger,
        UploadOptimizer $uploadOptimizer
    ): JsonResponse {
        try {
            $nom = $searchInputSanitizer->sanitizeKeyword($request->request->get('nom', ''), 255);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }

        $description = trim((string) $request->request->get('description', ''));
        $categorieId = $request->request->get('categorie');
        $photoFile = $request->files->get('photo');

        if ($photoError = $this->validateProfessionPhoto($photoFile, true)) {
            return $photoError;
        }

        $profession = new Profession();
        $profession->setProfession($nom);
        $profession->setDescription($description);
        $profession->setSlug(HashedSlugGenerator::generate());

        $categorie = null;
        if ($categorieId) {
            $categorie = $categoriesRepo->findOneBy(['slug' => $categorieId]);
            $profession->setCategorie($categorie);
        }

        if ($professionsRepo->findDuplicateByName($nom, $categorie?->getId())) {
            return $this->json([
                'success' => false,
                'message' => 'Cette profession existe déjà dans cette catégorie.',
            ], 409);
        }

        if ($photoFile) {
            $originalFilename = pathinfo(
                $photoFile->getClientOriginalName(),
                PATHINFO_FILENAME
            );

            $safeFilename = (string) $slugger->slug($originalFilename);
            $baseName = 'profession-' . ($safeFilename !== '' ? $safeFilename : 'photo') . '-' . bin2hex(random_bytes(5));

            try {
                $newFilename = $uploadOptimizer->store(
                    $photoFile,
                    (string) $this->getParameter('professions_photos_directory'),
                    $baseName
                );
            } catch (\Throwable) {
                return $this->photoValidationError(
                    'Impossible d’enregistrer la photo. Vérifiez le dossier d’upload puis réessayez.'
                );
            }

            $profession->setPhoto($newFilename);
        }

        $em->persist($profession);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Profession créée avec succès',
        ]);
    }

    /*
     * IMPORTANT :
     * {id} contient désormais le slug opaque reçu par le JavaScript existant.
     * Le nom de paramètre est conservé pour ne pas casser professions.js.
     */
    #[Route(
        '/{id}',
        name: 'admin_professions_show',
        methods: ['GET']
    )]
    public function show(
        #[MapEntity(mapping: ['id' => 'slug'])]
        Profession $profession,
        JobRepository $jobRepository
    ): JsonResponse {
        $jobsCount = $jobRepository->countByProfession(
            $profession->getId()
        );

        return $this->json([
            'id' => $profession->getSlug(),
            'nom' => $profession->getProfession(),
            'description' => $profession->getDescription(),
            'photo' => $profession->getPhoto(),
            'jobs_count' => $jobsCount,
            'categorie' => $profession->getCategorie()
                ? $profession->getCategorie()->getSlug()
                : null,
        ]);
    }

    #[Route(
        '/{id}/edit',
        name: 'admin_professions_edit',
        methods: ['POST']
    )]
    public function edit(
        Request $request,
        #[MapEntity(mapping: ['id' => 'slug'])]
        Profession $profession,
        EntityManagerInterface $em,
        CategorieRepository $categoriesRepo,
        ProfessionRepository $professionsRepo,
        SearchInputSanitizer $searchInputSanitizer,
        SluggerInterface $slugger,
        UploadOptimizer $uploadOptimizer
    ): JsonResponse {
        try {
            $nom = $searchInputSanitizer->sanitizeKeyword($request->request->get('nom', ''), 255);
        } catch (\InvalidArgumentException $exception) {
            return $this->json(['success' => false, 'message' => $exception->getMessage()], 422);
        }

        $description = trim((string) $request->request->get('description', ''));
        $categorieId = $request->request->get('categorie');
        $photoFile = $request->files->get('photo');

        $photoIsRequired = $profession->getPhoto() === null
            || trim((string) $profession->getPhoto()) === '';

        if ($photoError = $this->validateProfessionPhoto($photoFile, $photoIsRequired)) {
            return $photoError;
        }

        $profession->setProfession($nom);
        $profession->setDescription($description);

        $categorie = null;
        if ($categorieId) {
            $categorie = $categoriesRepo->findOneBy(['slug' => $categorieId]);
            $profession->setCategorie($categorie);
        } else {
            $profession->setCategorie(null);
        }

        if ($professionsRepo->findDuplicateByName($nom, $categorie?->getId(), $profession->getId())) {
            return $this->json([
                'success' => false,
                'message' => 'Cette profession existe déjà dans cette catégorie.',
            ], 409);
        }

        if ($photoFile) {
            $oldFilePath = null;

            if ($profession->getPhoto()) {
                $oldFilePath = $this->getParameter(
                    'professions_photos_directory'
                ) . '/' . $profession->getPhoto();
            }

            $originalFilename = pathinfo(
                $photoFile->getClientOriginalName(),
                PATHINFO_FILENAME
            );

            $safeFilename = (string) $slugger->slug($originalFilename);
            $baseName = 'profession-' . ($safeFilename !== '' ? $safeFilename : 'photo') . '-' . bin2hex(random_bytes(5));

            try {
                $newFilename = $uploadOptimizer->store(
                    $photoFile,
                    (string) $this->getParameter('professions_photos_directory'),
                    $baseName
                );
            } catch (\Throwable) {
                return $this->photoValidationError(
                    'Impossible d’enregistrer la nouvelle photo. L’ancienne photo a été conservée.'
                );
            }

            if ($oldFilePath && file_exists($oldFilePath)) {
                unlink($oldFilePath);
            }

            $profession->setPhoto($newFilename);
        }

        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Profession modifiée avec succès',
        ]);
    }

    #[Route(
        '/{id}',
        name: 'admin_professions_delete',
        methods: ['DELETE']
    )]
    public function delete(
        #[MapEntity(mapping: ['id' => 'slug'])]
        Profession $profession,
        EntityManagerInterface $em
    ): JsonResponse {
        if ($profession->getPhoto()) {
            $filePath = $this->getParameter(
                'professions_photos_directory'
            ) . '/' . $profession->getPhoto();

            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        $em->remove($profession);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Profession supprimée avec succès',
        ]);
    }

    #[Route(
        '/batch-delete',
        name: 'admin_professions_batch_delete',
        methods: ['POST']
    )]
    public function batchDelete(
        Request $request,
        ProfessionRepository $repository
    ): JsonResponse {
        $requestData = json_decode($request->getContent(), true);
        $ids = $requestData['ids'] ?? [];

        if (empty($ids)) {
            return $this->json([
                'success' => false,
                'message' => 'Aucune profession sélectionnée',
            ], 400);
        }

        $professions = $repository->findBy([
            'slug' => $ids,
        ]);

        foreach ($professions as $profession) {
            if ($profession->getPhoto()) {
                $filePath = $this->getParameter(
                    'professions_photos_directory'
                ) . '/' . $profession->getPhoto();

                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
        }

        $repository->deleteMultiple($ids);

        return $this->json([
            'success' => true,
            'message' => 'Professions supprimées avec succès',
        ]);
    }
    private function validateProfessionPhoto(
        mixed $photoFile,
        bool $required
    ): ?JsonResponse {
        if (!$photoFile instanceof UploadedFile) {
            if ($required) {
                return $this->photoValidationError(
                    'Veuillez ajouter une photo pour cette profession.'
                );
            }

            return null;
        }

        if (!$photoFile->isValid()) {
            return $this->photoValidationError(
                'Le téléchargement de la photo a échoué. Veuillez réessayer.'
            );
        }

        $fileSize = $photoFile->getSize();
        if ($fileSize === false || $fileSize > self::PHOTO_MAX_SIZE) {
            return $this->photoValidationError(
                'La photo ne doit pas dépasser 15 Mo avant compression.'
            );
        }

        if (@getimagesize($photoFile->getPathname()) === false) {
            return $this->photoValidationError(
                'Le fichier sélectionné n’est pas une image valide.'
            );
        }

        return null;
    }

    private function photoValidationError(string $message): JsonResponse
    {
        return $this->json([
            'success' => false,
            'message' => $message,
            'field' => 'photo',
            'errors' => [
                'photo' => $message,
            ],
        ], 422);
    }

}
