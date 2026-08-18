<?php

namespace App\Repository;

use App\Entity\Favorite;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class FavoriteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Favorite::class);
    }

    /**
     * Vérifie si un talent est déjà en favori pour un utilisateur
     */
    public function isFavorite(User $user, User $targetUser): bool
    {
        return (bool) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->andWhere('f.user = :u')
            ->andWhere('f.targetUser = :tu')
            ->setParameter('u', $user)
            ->setParameter('tu', $targetUser)
            ->getQuery()
            ->getSingleScalarResult();
    }


    /**
     * Compte le nombre de favoris d’un utilisateur
     */
    public function countByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('f')
            ->select('COUNT(f.id)')
            ->andWhere('f.user = :u')
            ->setParameter('u', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    
    /**
     * je récupère tous les favoris de l'utilisateur connecté avec préchargement du targetUser
     * @param User $me
     * @return array
     */
    public function findAllForUser(User $me): array
    {
        return $this->createQueryBuilder('f')
            ->leftJoin('f.targetUser', 'tu')->addSelect('tu')
            ->andWhere('f.user = :me')
            ->setParameter('me', $me)
            ->orderBy('f.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    
    /**
     * je récupère le liste des utilisateurs (f.user) qui ont ajouté mon compte (f.targetUser) en favori
     * @param User $me
     * @return array
     */
    public function findUsersWhoFavoritedMe(User $me): array
    {
        return $this->createQueryBuilder('f')
            ->leftJoin('f.user', 'u')->addSelect('u')
            ->andWhere('f.targetUser = :me')
            ->setParameter('me', $me)
            ->orderBy('f.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

     /**
     * Favoris récents d’un utilisateur
     */
    public function findRecentFavorites(User $user, int $limit = 8): array
    {
        return $this->createQueryBuilder('f')
            ->leftJoin('f.targetUser', 'tu')->addSelect('tu')
            ->andWhere('f.user = :user')
            ->setParameter('user', $user)
            ->orderBy('f.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

}
