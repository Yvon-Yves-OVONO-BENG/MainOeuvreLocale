<?php

namespace App\Controller\Web;

use App\Entity\AdvertisingCampaign;
use App\Entity\AdvertisingSpace;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;

final class AdvertisingController extends AbstractController
{
    #[Route('/advertising/slot/{code}', name: 'advertising_slot', methods: ['GET'])]
    public function slot(string $code, EntityManagerInterface $em): JsonResponse
    {
        $space = $em->getRepository(AdvertisingSpace::class)->findOneBy(['code' => $code, 'active' => true]);
        if (!$space) return $this->json(['ok' => true, 'campaign' => null]);
        $now = new \DateTimeImmutable();
        $campaign = $em->getRepository(AdvertisingCampaign::class)->createQueryBuilder('c')
            ->andWhere('c.space = :space AND c.status = :status AND c.startsAt <= :now AND c.endsAt >= :now')
            ->setParameter('space', $space)->setParameter('status', 'active')->setParameter('now', $now)
            ->orderBy('c.impressions', 'ASC')->setMaxResults(1)->getQuery()->getOneOrNullResult();
        if (!$campaign) return $this->json(['ok' => true, 'campaign' => null]);
        $campaign->incrementImpressions(); $em->flush();
        return $this->json(['ok' => true, 'campaign' => ['id' => $campaign->getId(), 'title' => $campaign->getTitle(), 'image' => $campaign->getImage(), 'clickUrl' => $this->generateUrl('advertising_click', ['id' => $campaign->getId()])]]);
    }

    #[Route('/advertising/click/{id}', name: 'advertising_click', methods: ['GET'])]
    public function click(AdvertisingCampaign $campaign, EntityManagerInterface $em): RedirectResponse
    {
        $campaign->incrementClicks(); $em->flush();
        return $this->redirect($campaign->getTargetUrl());
    }
}
