<?php

namespace App\Command;

use App\Entity\UptimeCheck;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsCommand(name: 'app:monitor:uptime')]
class RecordUptimeChecksCommand extends Command
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly EntityManagerInterface $em
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $targets = [
            [
                'service' => 'Homepage',
                'url' => 'https://ton-domaine.com/',
            ],
            [
                'service' => 'Login',
                'url' => 'https://ton-domaine.com/login',
            ],
            [
                'service' => 'API',
                'url' => 'https://ton-domaine.com/api',
            ],
        ];

        foreach ($targets as $target) {
            $check = new UptimeCheck();
            $check->setServiceName($target['service']);
            $check->setUrl($target['url']);
            $check->setCheckedAt(new \DateTimeImmutable());

            $start = microtime(true);

            try {
                $response = $this->httpClient->request('GET', $target['url'], [
                    'timeout' => 10,
                ]);

                $statusCode = $response->getStatusCode();
                $durationMs = (int) round((microtime(true) - $start) * 1000);

                $check->setStatusCode($statusCode);
                $check->setResponseTimeMs($durationMs);
                $check->setIsUp($statusCode >= 200 && $statusCode < 500);
            } catch (\Throwable $e) {
                $durationMs = (int) round((microtime(true) - $start) * 1000);

                $check->setIsUp(false);
                $check->setResponseTimeMs($durationMs);
                $check->setErrorMessage($e->getMessage());
            }

            $this->em->persist($check);
        }

        $this->em->flush();

        $output->writeln('Uptime checks enregistrés.');
        return Command::SUCCESS;
    }
}