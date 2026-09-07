<?php

namespace App\Controller\Web\Admin;

use App\Entity\AdvertisingCampaign;
use App\Entity\AdvertisingSpace;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/advertising', name: 'admin_advertising_')]
final class AdvertisingAdminController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET', 'POST'])]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('advertising_admin', (string) $request->request->get('_token'))) throw $this->createAccessDeniedException();
            $kind = (string) $request->request->get('kind');
            if ($kind === 'space') {
                $space = (new AdvertisingSpace())->setCode((string) $request->request->get('code'))->setName((string) $request->request->get('name'))->setPosition((string) $request->request->get('position'))->setDailyPrice((string) $request->request->get('dailyPrice', '0'));
                $em->persist($space);
            } elseif ($kind === 'campaign') {
                $space = $em->getRepository(AdvertisingSpace::class)->find((int) $request->request->get('space'));
                $advertiser = $em->getRepository(User::class)->find((int) $request->request->get('advertiser'));
                if ($space && $advertiser) {
                    $campaign = (new AdvertisingCampaign())->setSpace($space)->setAdvertiser($advertiser)->setTitle((string) $request->request->get('title'))->setImage((string) $request->request->get('image'))->setTargetUrl((string) $request->request->get('targetUrl'))->setStartsAt(new \DateTimeImmutable((string) $request->request->get('startsAt')))->setEndsAt(new \DateTimeImmutable((string) $request->request->get('endsAt')))->setStatus('pending');
                    $em->persist($campaign);
                }
            }
            $em->flush();
            return $this->redirectToRoute('admin_advertising_index');
        }
        return $this->render('admin/advertising/index.html.twig', ['spaces' => $em->getRepository(AdvertisingSpace::class)->findBy([], ['id' => 'DESC']), 'campaigns' => $em->getRepository(AdvertisingCampaign::class)->findBy([], ['id' => 'DESC'])]);
    }

    #[Route('/campaign/{id}/{status}', name: 'status', requirements: ['status' => 'active|rejected|paused'], methods: ['POST'])]
    public function status(AdvertisingCampaign $campaign, string $status, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if (!$this->isCsrfTokenValid('advertising_status_'.$campaign->getId(), (string) $request->request->get('_token'))) throw $this->createAccessDeniedException();
        $campaign->setStatus($status); $em->flush();
        return $this->redirectToRoute('admin_advertising_index');
    }
}
