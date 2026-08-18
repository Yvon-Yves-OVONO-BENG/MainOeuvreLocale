<?php

namespace App\Command;

use App\Service\AppointmentSchedulerService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:appointments:send-reminders', description: 'Envoie les rappels J-1 et H-1 des rendez-vous')]
class AppointmentReminderCommand extends Command
{
    public function __construct(private AppointmentSchedulerService $scheduler)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = $this->scheduler->sendAutomaticReminders();

        $output->writeln(sprintf('Rappels envoyés - J-1: %d | H-1: %d', $result['j1'] ?? 0, $result['h1'] ?? 0));

        return Command::SUCCESS;
    }
}