<?php

namespace App\Command;

use App\Entity\AccountDeletionRequest;
use App\Repository\AccountDeletionRequestRepository;
use App\Service\AccountAnonymizer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:accounts:purge',
    description: 'Traite les demandes APPROVED arrivées à échéance (anonymisation).'
)]
class PurgeAccountsCommand extends Command
{
    public function __construct(
        private AccountDeletionRequestRepository $repo,
        private AccountAnonymizer $anonymizer,
        private EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Max à traiter', 50)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simuler sans modifier');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = (int) $input->getOption('limit');
        $dry = (bool) $input->getOption('dry-run');

        $items = $this->repo->findDueForProcessing($limit);

        $output->writeln(sprintf('Found %d due requests.', count($items)));

        foreach ($items as $r) {
            if (!$r instanceof AccountDeletionRequest) continue;

            $user = $r->getUser();
            $output->writeln(sprintf('Processing request #%d for user #%d', $r->getId(), $user->getId()));

            if (!$dry) {
                $this->anonymizer->anonymize($user);

                $r->setStatus(AccountDeletionRequest::STATUS_DONE);
                $r->setProcessedAt(new \DateTimeImmutable());

                $this->em->flush();
            }
        }

        $output->writeln($dry ? 'Dry-run done.' : 'Done.');
        return Command::SUCCESS;
    }
}