<?php

namespace App\Service\Newsletter;

use App\Entity\Job;
use App\Entity\NewsletterSubscriber;
use App\Entity\Profession;
use App\Entity\User;
use App\Repository\NewsletterSubscriberRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class NewsletterService
{
    public function __construct(
        private EntityManagerInterface $em,
        private NewsletterSubscriberRepository $newsletterSubscriberRepository,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger,
        #[Autowire('%app.contact_from_email%')]
        private string $fromEmail,
    ) {
    }

    public function subscribe(
        string $email,
        ?User $user = null,
        string $source = 'footer',
    ): string {
        $email = mb_strtolower(trim($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('Adresse email invalide.');
        }

        $profession = $user?->getProfessionalProfile()?->getProfession();
        $subscriber = $this->newsletterSubscriberRepository->findOneByEmail($email);

        if ($subscriber instanceof NewsletterSubscriber) {
            $wasActive = $subscriber->isActive();
            $sameProfession = $subscriber->getProfession()?->getId() === $profession?->getId();

            if (!$wasActive) {
                $subscriber->reactivate($source);
            }

            $subscriber
                ->setEmail($email)
                ->setProfession($profession)
                ->setUser($user)
                ->setSource($source);

            if (!$subscriber->getUnsubscribeToken()) {
                $subscriber->regenerateUnsubscribeToken();
            }

            $this->em->flush();

            if (!$wasActive) {
                return 'reactivated';
            }

            return $sameProfession ? 'already_active' : 'updated';
        }

        $subscriber = (new NewsletterSubscriber())
            ->setEmail($email)
            ->setSource($source)
            ->setProfession($profession)
            ->setUser($user);

        $this->em->persist($subscriber);
        $this->em->flush();

        return 'created';
    }

    public function unsubscribeByToken(string $token): bool
    {
        if ('' === trim($token)) {
            return false;
        }

        $subscriber = $this->newsletterSubscriberRepository->findOneByToken($token);

        if (!$subscriber) {
            return false;
        }

        return $this->unsubscribe($subscriber);
    }

    public function unsubscribe(NewsletterSubscriber $subscriber): bool
    {
        if ($subscriber->isActive()) {
            $subscriber->unsubscribe();
            $this->em->flush();
        }

        return true;
    }

    public function sendJobPublishedNewsletter(Job $job): int
    {
        $profession = $job->getProfession();

        if (!$profession instanceof Profession) {
            $this->logger->warning('Alerte offre ignorée : profession absente.', [
                'job_id' => $job->getId(),
            ]);

            return 0;
        }

        $subscribers = $this->newsletterSubscriberRepository
            ->findActiveSubscribersForProfession($profession);

        if ([] === $subscribers) {
            return 0;
        }

        $jobTitle = method_exists($job, 'getTitle') && $job->getTitle()
            ? (string) $job->getTitle()
            : 'Nouvelle offre publiée';

        $jobDescription = method_exists($job, 'getDescription') && $job->getDescription()
            ? (string) $job->getDescription()
            : '';

        $platformUrl = $job->getSlug()
            ? $this->urlGenerator->generate(
                'job_detail',
                ['slug' => $job->getSlug()],
                UrlGeneratorInterface::ABSOLUTE_URL
            )
            : $this->urlGenerator->generate('accueil', [], UrlGeneratorInterface::ABSOLUTE_URL);

        $sent = 0;

        foreach ($subscribers as $subscriber) {
            try {
                if (!$subscriber->getUnsubscribeToken()) {
                    $subscriber->regenerateUnsubscribeToken();
                    $this->em->flush();
                }

                $unsubscribeUrl = $this->urlGenerator->generate(
                    'newsletter_unsubscribe',
                    ['token' => $subscriber->getUnsubscribeToken()],
                    UrlGeneratorInterface::ABSOLUTE_URL
                );

                $email = (new TemplatedEmail())
                    ->from(new Address($this->fromEmail, "Main d'Œuvre Locale"))
                    ->to(new Address($subscriber->getEmail()))
                    ->subject('Nouvelle offre '.$profession->getProfession().' : '.$jobTitle)
                    ->htmlTemplate('emails/newsletter/job_published.html.twig')
                    ->context([
                        'jobTitle' => $jobTitle,
                        'jobDescription' => $jobDescription,
                        'professionName' => $profession->getProfession(),
                        'platformUrl' => $platformUrl,
                        'unsubscribeUrl' => $unsubscribeUrl,
                    ]);

                $this->mailer->send($email);
                ++$sent;
            } catch (\Throwable $e) {
                $this->logger->error('Erreur envoi alerte offre', [
                    'email' => $subscriber->getEmail(),
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $sent;
    }
}
