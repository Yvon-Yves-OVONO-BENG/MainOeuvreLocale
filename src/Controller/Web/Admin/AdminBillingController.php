<?php

namespace App\Controller\Web\Admin;

use App\Repository\InvoicesRepository;
use App\Repository\PaymentRepository;
use App\Repository\SubscriptionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin', name: 'admin_')]
class AdminBillingController extends AbstractController
{
    #[Route('/payments', name: 'payments_index', methods: ['GET'])]
    public function payments(Request $request, PaymentRepository $paymentRepository): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = min(100, max(10, (int) $request->query->get('perPage', 20)));
        $offset = ($page - 1) * $perPage;

        $qb = $paymentRepository->createQueryBuilder('p')
            ->leftJoin('p.user', 'u')->addSelect('u')
            ->leftJoin('p.provider', 'pr')->addSelect('pr')
            ->leftJoin('p.subscription', 's')->addSelect('s')
            ->leftJoin('p.statusPayment', 'sp')->addSelect('sp')
            ->orderBy('p.paidAt', 'DESC');

        if ($q !== '') {
            $qb->andWhere('
                u.email LIKE :q
                OR p.amount LIKE :q
                OR p.currency LIKE :q
                OR pr.provider LIKE :q
            ')
            ->setParameter('q', '%' . $q . '%');
        }

        $total = (int) (clone $qb)
            ->select('COUNT(DISTINCT p.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $qb
            ->setFirstResult($offset)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        $pages = max(1, (int) ceil($total / $perPage));

        return $this->render('admin/payments_index.html.twig', [
            'items' => $items,
            'q' => $q,
            'page' => $page,
            'pages' => $pages,
            'perPage' => $perPage,
            'total' => $total,
        ]);
    }

    #[Route('/invoices', name: 'invoices_index', methods: ['GET'])]
    public function invoices(Request $request, InvoicesRepository $invoicesRepository): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = min(100, max(10, (int) $request->query->get('perPage', 20)));
        $offset = ($page - 1) * $perPage;

        $qb = $invoicesRepository->createQueryBuilder('i')
            ->leftJoin('i.user', 'u')->addSelect('u')
            ->leftJoin('i.payment', 'p')->addSelect('p')
            ->orderBy('i.createdAt', 'DESC');

        if ($q !== '') {
            $qb->andWhere('
                u.email LIKE :q
                OR i.invoiceNumber LIKE :q
                OR i.slug LIKE :q
                OR p.amount LIKE :q
            ')
            ->setParameter('q', '%' . $q . '%');
        }

        $total = (int) (clone $qb)
            ->select('COUNT(DISTINCT i.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $qb
            ->setFirstResult($offset)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        $pages = max(1, (int) ceil($total / $perPage));

        return $this->render('admin/invoices_index.html.twig', [
            'items' => $items,
            'q' => $q,
            'page' => $page,
            'pages' => $pages,
            'perPage' => $perPage,
            'total' => $total,
        ]);
    }

    #[Route('/subscriptions', name: 'subscriptions_index', methods: ['GET'])]
    public function subscriptions(Request $request, SubscriptionRepository $subscriptionRepository): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = min(100, max(10, (int) $request->query->get('perPage', 20)));
        $offset = ($page - 1) * $perPage;

        $qb = $subscriptionRepository->createQueryBuilder('s')
            ->leftJoin('s.user', 'u')->addSelect('u')
            ->orderBy('s.id', 'DESC');

        if ($q !== '') {
            $qb->andWhere('u.email LIKE :q')
               ->setParameter('q', '%' . $q . '%');
        }

        $total = (int) (clone $qb)
            ->select('COUNT(DISTINCT s.id)')
            ->resetDQLPart('orderBy')
            ->getQuery()
            ->getSingleScalarResult();

        $items = $qb
            ->setFirstResult($offset)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        $pages = max(1, (int) ceil($total / $perPage));

        return $this->render('admin/subscriptions_index.html.twig', [
            'items' => $items,
            'q' => $q,
            'page' => $page,
            'pages' => $pages,
            'perPage' => $perPage,
            'total' => $total,
        ]);
    }
}