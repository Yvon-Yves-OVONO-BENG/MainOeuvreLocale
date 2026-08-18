<?php

namespace App\Controller\Web\Talent;

use App\Entity\ProfessionalMedia;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class TalentPortfolioPublicController extends AbstractController
{
    #[Route(
        '/talent/{slug}/portfolio',
        name: 'talent_portfolio_show',
        methods: ['GET'],
        requirements: ['slug' => '[a-f0-9]{64}']
    )]
    public function show(
        string $slug,
        UserRepository $userRepository,
        EntityManagerInterface $em
    ): Response {
        $user = $userRepository->findOneBy(['slug' => $slug]);
        if (!$user instanceof User) {
            throw $this->createNotFoundException('Talent introuvable.');
        }

        $pro = $user->getProfessionalProfile();
        if (!$pro) {
            throw $this->createNotFoundException('Portfolio introuvable.');
        }

        $media = $em->getRepository(ProfessionalMedia::class)
            ->createQueryBuilder('m')
            ->andWhere('m.professionalProfile = :p')
            ->setParameter('p', $pro)
            ->orderBy('m.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('portfolio/public_show.html.twig', [
            'pro' => $pro,
            'personal' => $user->getPersonalProfile(),
            'media' => $media,
        ]);
    }
}
