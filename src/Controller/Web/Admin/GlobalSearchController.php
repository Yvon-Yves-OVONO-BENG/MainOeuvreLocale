<?php

namespace App\Controller\Web\Admin;

use App\Repository\InvoicesRepository;
use App\Repository\UserRepository;
use App\Repository\JobRepository;
use App\Repository\PaymentRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class GlobalSearchController extends AbstractController
{
    public function search(
        Request $request,
        UserRepository $userRepository,
        JobRepository $jobRepository,
        PaymentRepository $paymentRepository,
        InvoicesRepository $invoicesRepository
    ): JsonResponse {

        $q = trim($request->query->get('q',''));

        if(strlen($q) < 2){
            return $this->json([]);
        }

        return $this->json([
            'users' => $userRepository->searchAdmin($q),
            'companies' => $userRepository->searchAdmin($q),
            'jobs' => $jobRepository->searchAdmin($q),
            'transactions' => $paymentRepository->searchAdmin($q),
            'tickets' => $invoicesRepository->searchAdmin($q),
        ]);
    }
}