<?php

namespace App\Controller\Web\Admin;



use App\Service\AdminSecurityService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

#[Route('/admin', name: 'admin_')]
class AdminSecurityController extends AbstractController
{
    public function __construct(
        private readonly AdminSecurityService $securityService
    ) {
    }

    #[Route('/roles', name: 'roles_index', methods: ['GET'])]
    public function roles(): Response
    {
        $this->denyUnlessAdminOrSuperAdmin();

        return $this->render('admin/security/roles.html.twig', $this->securityService->getRolesPageData());
    }

    #[Route('/permissions', name: 'permissions_index', methods: ['GET'])]
    public function permissions(): Response
    {
        $this->denyUnlessAdminOrSuperAdmin();

        return $this->render('admin/security/permissions.html.twig', $this->securityService->getPermissionsPageData());
    }

    #[Route('/sessions', name: 'sessions_index', methods: ['GET'])]
    public function sessions(): Response
    {
        $this->denyUnlessAdminOrSuperAdmin();

        return $this->render('admin/security/sessions.html.twig', $this->securityService->getSessionsPageData());
    }

    #[Route('/security/incidents', name: 'security_incidents', methods: ['GET'])]
    public function incidents(): Response
    {
        $this->denyUnlessAdminOrSuperAdmin();

        return $this->render('admin/security/incidents.html.twig', $this->securityService->getIncidentsPageData());
    }

    #[Route('/2fa', name: '2fa_index', methods: ['GET'])]
    public function twoFactor(): Response
    {
        $this->denyUnlessAdminOrSuperAdmin();

        return $this->render('admin/security/two_factor.html.twig', $this->securityService->getTwoFactorPageData());
    }

    #[Route('/admin/login', name: 'admin_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        return $this->render('admin/security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/admin/logout', name: 'admin_logout')]
    public function logout(): void
    {
        throw new \LogicException('Cette méthode peut rester vide, elle est interceptée par le firewall.');
    }

    private function denyUnlessAdminOrSuperAdmin(): void
    {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_SUPER_ADMIN')) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }
    }
}