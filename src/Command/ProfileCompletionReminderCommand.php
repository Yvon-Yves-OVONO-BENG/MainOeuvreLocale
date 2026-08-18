<?php

namespace App\Command;

use App\Service\ProfileCompletionReminderService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:profiles:send-completion-reminders',
    description: 'Relance par chat et e-mail les utilisateurs dont le profil reste incomplet.',
)]
final class ProfileCompletionReminderCommand extends Command
{
    /**
     * Initialise la commande avec le service métier de relance.
     */
    public function __construct(private readonly ProfileCompletionReminderService $reminderService)
    {
        parent::__construct();
    }

    /**
     * Déclare les délais, la limite d'envoi et le mode de simulation.
     */
    protected function configure(): void
    {
        $this
            ->addOption('after-hours', null, InputOption::VALUE_REQUIRED, 'Attendre ce nombre d’heures après l’inscription.', '24')
            ->addOption('cooldown-days', null, InputOption::VALUE_REQUIRED, 'Attendre ce nombre de jours avant une nouvelle relance.', '7')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Nombre maximal de comptes traités par exécution.', '200')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Lister les relances dues sans envoyer de message.');
    }

    /**
     * Exécute la campagne et affiche un bilan lisible dans les journaux CRON.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $stats = $this->reminderService->sendDueReminders(
            (int) $input->getOption('after-hours'),
            (int) $input->getOption('cooldown-days'),
            (int) $input->getOption('limit'),
            (bool) $input->getOption('dry-run'),
        );

        $io->title($stats['dryRun'] ? 'Simulation des relances de profil' : 'Relances de profil terminées');
        $io->table(
            ['Comptes vérifiés', 'À relancer', 'Traités', 'Chat', 'E-mail', 'Échecs', 'Déjà complets', 'En pause'],
            [[
                $stats['scanned'],
                $stats['due'],
                $stats['sent'],
                $stats['chat'],
                $stats['email'],
                $stats['failed'],
                $stats['complete'],
                $stats['cooldown'],
            ]],
        );

        $io->success($stats['dryRun'] ? 'Aucun message n’a été envoyé.' : 'La campagne a été exécutée.');

        return Command::SUCCESS;
    }
}
