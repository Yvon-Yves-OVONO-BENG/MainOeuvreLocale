<?php

namespace App\Controller\Web\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin', name: 'admin_')]
class AdminPlatformCenterController extends AbstractController
{
    #[Route('/settings', name: 'settings_index', methods: ['GET'])]
    public function settings(): Response
    {
        $this->denyUnlessAdminOrSuperAdmin();

        $stats = [
            'groups' => 5,
            'sensitive' => 3,
            'lastUpdate' => new \DateTimeImmutable('-2 hours'),
        ];

        $groups = [
            [
                'title' => 'Général',
                'icon' => '🧭',
                'description' => 'Nom de la plateforme, branding, mode maintenance, paramètres globaux.',
                'items' => [
                    ['label' => 'Nom de la plateforme', 'value' => 'Main d’Oeuvre Locale'],
                    ['label' => 'Environnement', 'value' => 'Production'],
                    ['label' => 'Mode maintenance', 'value' => 'Désactivé'],
                ],
            ],
            [
                'title' => 'Sécurité',
                'icon' => '🔒',
                'description' => 'Sessions, contrôle d’accès, vérifications sensibles, journalisation.',
                'items' => [
                    ['label' => 'Journalisation', 'value' => 'Activée'],
                    ['label' => '2FA comptes sensibles', 'value' => 'Obligatoire'],
                    ['label' => 'Durée session admin', 'value' => '8 heures'],
                ],
            ],
            [
                'title' => 'Modération',
                'icon' => '🧹',
                'description' => 'Mots-clés, règles anti-spam, files de revue et sanctions.',
                'items' => [
                    ['label' => 'Mots-clés sensibles', 'value' => 'Actifs'],
                    ['label' => 'Anti-spam', 'value' => 'Actif'],
                    ['label' => 'Validation manuelle', 'value' => 'Partielle'],
                ],
            ],
            [
                'title' => 'Finance',
                'icon' => '💳',
                'description' => 'Paiements, abonnements, devises, facturation et providers.',
                'items' => [
                    ['label' => 'Devise principale', 'value' => 'XAF'],
                    ['label' => 'Facturation', 'value' => 'Active'],
                    ['label' => 'Remboursements', 'value' => 'Sous validation'],
                ],
            ],
            [
                'title' => 'Support',
                'icon' => '🎧',
                'description' => 'Tickets, FAQ, templates et flux opérationnels support.',
                'items' => [
                    ['label' => 'Boîte support', 'value' => 'Active'],
                    ['label' => 'Templates', 'value' => 'Disponibles'],
                    ['label' => 'FAQ interne', 'value' => 'Publiée'],
                ],
            ],
        ];

        return $this->render('admin/center/settings.html.twig', [
            'stats' => $stats,
            'groups' => $groups,
            'currentSection' => 'settings',
        ]);
    }

    #[Route('/backups', name: 'backups_index', methods: ['GET'])]
    public function backups(): Response
    {
        $this->denyUnlessAdminOrSuperAdmin();

        $stats = [
            'total' => 6,
            'successful' => 5,
            'failed' => 1,
        ];

        $backups = [
            [
                'name' => 'backup-prod-db-2026-03-08.sql.gz',
                'type' => 'Database',
                'provider' => 'Object Storage',
                'size' => '1.8 GB',
                'status' => 'success',
                'createdAt' => new \DateTimeImmutable('-2 hours'),
            ],
            [
                'name' => 'backup-prod-files-2026-03-08.tar.gz',
                'type' => 'Files',
                'provider' => 'Object Storage',
                'size' => '8.4 GB',
                'status' => 'success',
                'createdAt' => new \DateTimeImmutable('-3 hours'),
            ],
            [
                'name' => 'backup-prod-config-2026-03-08.zip',
                'type' => 'Config',
                'provider' => 'S3 Mirror',
                'size' => '42 MB',
                'status' => 'success',
                'createdAt' => new \DateTimeImmutable('-3 hours'),
            ],
            [
                'name' => 'backup-prod-db-2026-03-07.sql.gz',
                'type' => 'Database',
                'provider' => 'Object Storage',
                'size' => '1.8 GB',
                'status' => 'success',
                'createdAt' => new \DateTimeImmutable('-1 day'),
            ],
            [
                'name' => 'backup-prod-files-2026-03-07.tar.gz',
                'type' => 'Files',
                'provider' => 'Object Storage',
                'size' => '8.3 GB',
                'status' => 'success',
                'createdAt' => new \DateTimeImmutable('-1 day'),
            ],
            [
                'name' => 'backup-prod-logs-2026-03-07.tar.gz',
                'type' => 'Logs',
                'provider' => 'Cold Storage',
                'size' => '620 MB',
                'status' => 'failed',
                'createdAt' => new \DateTimeImmutable('-1 day'),
            ],
        ];

        return $this->render('admin/center/backups.html.twig', [
            'stats' => $stats,
            'backups' => $backups,
            'currentSection' => 'backups',
        ]);
    }

    #[Route('/integrations', name: 'integrations_index', methods: ['GET'])]
    public function integrations(): Response
    {
        $this->denyUnlessAdminOrSuperAdmin();

        $stats = [
            'connected' => 4,
            'warning' => 1,
            'disabled' => 1,
        ];

        $integrations = [
            [
                'name' => 'Stripe',
                'category' => 'Paiement',
                'status' => 'connected',
                'env' => 'Production',
                'lastSync' => new \DateTimeImmutable('-10 minutes'),
            ],
            [
                'name' => 'Orange Money',
                'category' => 'Paiement',
                'status' => 'connected',
                'env' => 'Production',
                'lastSync' => new \DateTimeImmutable('-18 minutes'),
            ],
            [
                'name' => 'MTN MoMo',
                'category' => 'Paiement',
                'status' => 'warning',
                'env' => 'Production',
                'lastSync' => new \DateTimeImmutable('-2 hours'),
            ],
            [
                'name' => 'SMTP Mailer',
                'category' => 'Email',
                'status' => 'connected',
                'env' => 'Production',
                'lastSync' => new \DateTimeImmutable('-22 minutes'),
            ],
            [
                'name' => 'Webhook Partenaire RH',
                'category' => 'API',
                'status' => 'disabled',
                'env' => 'Inactive',
                'lastSync' => null,
            ],
        ];

        return $this->render('admin/center/integrations.html.twig', [
            'stats' => $stats,
            'integrations' => $integrations,
            'currentSection' => 'integrations',
        ]);
    }

    private function denyUnlessAdminOrSuperAdmin(): void
    {
        if (!$this->isGranted('ROLE_ADMIN') && !$this->isGranted('ROLE_SUPER_ADMIN')) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }
    }
}
