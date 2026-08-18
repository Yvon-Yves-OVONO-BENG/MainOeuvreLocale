<?php

namespace App\Controller\Web;

use App\Repository\ProfessionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ProfessionByCategoryController extends AbstractController
{
    #[Route('/api/categories/{id}/professions', name: 'api_professions_by_category', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function __invoke(int $id, ProfessionRepository $repository): JsonResponse
    {
        $professions = $repository->createQueryBuilder('p')
            ->andWhere('IDENTITY(p.categorie) = :category')
            ->setParameter('category', $id)
            ->orderBy('p.profession', 'ASC')
            ->getQuery()->getResult();

        return $this->json(array_map(static fn ($profession): array => [
            'id' => $profession->getId(),
            'label' => $profession->getProfession(),
        ], $professions));
    }
}
