<?php

namespace App\Controller\Web\Admin;

use App\Repository\NewsletterSubscriberRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(new Expression('is_granted("ROLE_ADMIN") or is_granted("ROLE_SUPER_ADMIN")'))]
class NewsletterAdminController extends AbstractController
{
    #[Route('/admin/newsletter/abonnes', name: 'admin_newsletter_index', methods: ['GET'])]
    public function index(NewsletterSubscriberRepository $newsletterSubscriberRepository): Response
    {
        $subscribers = $newsletterSubscriberRepository->findAllForAdmin();

        return $this->render('admin/newsletter/index.html.twig', [
            'subscribers' => $subscribers,
            'activeCount' => $newsletterSubscriberRepository->count(['isActive' => true]),
            'inactiveCount' => $newsletterSubscriberRepository->count(['isActive' => false]),
            'totalCount' => $newsletterSubscriberRepository->count([]),
        ]);
    }
}
