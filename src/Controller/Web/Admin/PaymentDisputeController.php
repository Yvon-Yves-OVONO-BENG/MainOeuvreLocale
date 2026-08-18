<?php

namespace App\Controller\Web\Admin;

use App\Entity\PaymentDispute;
use App\Entity\User;
use App\Form\PaymentDisputeType;
use App\Repository\PaymentDisputeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;

#[Route('/my/disputes', name: 'user_payment_dispute_')]
class PaymentDisputeController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(PaymentDisputeRepository $repo): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        return $this->render('user/payment_dispute_index.html.twig', [
            'items' => $user ? $repo->findForUser($user) : [],
        ]);
    }

    #[Route('/new', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        $dispute = new PaymentDispute();
        if ($user) {
            $dispute->setUser($user);
        }
        $dispute->setSource(PaymentDispute::SOURCE_USER);

        $form = $this->createForm(PaymentDisputeType::class, $dispute, [
            'is_admin' => false,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($dispute->getPayment() && !$dispute->getUser()) {
                $dispute->setUser($dispute->getPayment()->getUser());
            }

            $em->persist($dispute);
            $em->flush();

            $this->addFlash('success', 'Votre litige a été enregistré.');

            return $this->redirectToRoute('user_payment_dispute_index');
        }

        return $this->render('user/payment_dispute_new.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}