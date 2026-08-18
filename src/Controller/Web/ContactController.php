<?php

namespace App\Controller\Web;

use App\Entity\ContactMessage;
use App\Form\ContactType;
use App\Model\ContactRequest;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

final class ContactController extends AbstractController
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly EntityManagerInterface $em,
        #[Autowire('%app.contact_to_email%')]
        private readonly string $contactToEmail,
        #[Autowire('%app.contact_from_email%')]
        private readonly string $contactFromEmail,
    ) {
    }

    #[Route('/contact', name: 'contact_page', methods: ['GET', 'POST'])]
    public function index(Request $request, TranslatorInterface $translator): Response
    {
        $data = new ContactRequest();

        $form = $this->createForm(ContactType::class, $data, [
            'attr' => [
                'novalidate' => 'novalidate',
                'data-turbo' => 'false',
            ],
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $contactMessage = (new ContactMessage())
                ->setName($data->getName())
                ->setEmail($data->getEmail())
                ->setPhone($data->getPhone())
                ->setSubject($data->getSubject())
                ->setMessage($data->getMessage())
                ->setStatus(ContactMessage::STATUS_NEW)
                ->setIsRead(false)
                ->setIpAddress($request->getClientIp())
                ->setUserAgent(substr((string) $request->headers->get('User-Agent'), 0, 255));

            $this->em->persist($contactMessage);
            $this->em->flush();

            $subjectLabel = $contactMessage->getSubjectLabel();

            $email = (new Email())
                ->from($this->contactFromEmail)
                ->to("ovono770@gmail.com")
                ->replyTo((string) $contactMessage->getEmail())
                ->subject(sprintf(
                    '[Contact #%d] %s — %s',
                    $contactMessage->getId(),
                    $subjectLabel,
                    (string) $contactMessage->getName()
                ))
                ->html($this->renderView('emails/contact_notification.html.twig', [
                    'messageEntity' => $contactMessage,
                    'subjectLabel' => $subjectLabel,
                ]));

            try {
                $this->mailer->send($email);

                $this->addFlash(
                    'success',
                    $translator->trans('Votre message a bien été envoyé et enregistré. Notre équipe vous répondra très bientôt.')
                );
            } catch (TransportExceptionInterface) {
                $this->addFlash(
                    'warning',
                    $translator->trans('Votre message a bien été enregistré, mais l’email de notification n’a pas pu être envoyé.')
                );
            }

            return $this->redirectToRoute('contact_page');
        }

        return $this->render('contact/contact.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}