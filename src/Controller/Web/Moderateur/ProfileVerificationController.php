<?php

namespace App\Controller\Web\Moderateur;

use App\Entity\PersonalProfile;
use App\Entity\ProfessionalProfile;
use App\Entity\User;
use App\Repository\PersonalProfileRepository;
use App\Repository\ProfessionalProfileRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use App\Entity\Conversation;
use App\Entity\Message;
use App\Repository\ConversationRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/moderateur/verifications', name: 'moderateur_verifications_')]
#[IsGranted(new Expression('is_granted("ROLE_MODERATEUR") or is_granted("ROLE_ADMIN") or is_granted("ROLE_SUPER_ADMIN")'))]
class ProfileVerificationController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        UserRepository $userRepo,
        PersonalProfileRepository $ppRepo,
        ProfessionalProfileRepository $proRepo
    ): Response {
        $q     = trim((string) $request->query->get('q', ''));
        $role  = (string) $request->query->get('role', '');          // talent|particulier|company|''
        $state = (string) $request->query->get('state', 'pending');  // pending|verified|rejected|all
        $page  = max(1, (int) $request->query->get('page', 1));
        $limit = min(50, max(10, (int) $request->query->get('limit', 10)));
        $offset = ($page - 1) * $limit;

        // ✅ total
        $total = $userRepo->countForVerificationList($q, $role, $state);

        // ✅ rows (users + personalProfile + professionalProfile)
        $rows = $userRepo->findForVerificationList($q, $role, $state, $limit, $offset);

        $pages = max(1, (int) ceil($total / $limit));

        // ✅ stats chips
        $stats = [
            'cni' => $ppRepo->getCniStats(),
            'cv'  => $proRepo->getCvStats(),
        ];

        return $this->render('moderateur/verifications.html.twig', [
            'rows' => $rows,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'limit' => $limit,
            'q' => $q,
            'role' => $role,
            'state' => $state,
            'stats' => $stats,
        ]);
    }

    /**
     * ✅ Afficher la page vérification via ID user
     * URL: /moderateur/verifications/u/123
     * Route name: moderateur_verifications_show
     */
    #[Route('/u/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function showById(int $id, UserRepository $userRepo): Response
    {
        $u = $userRepo->findOneWithProfiles($id);
        if (!$u) {
            throw new NotFoundHttpException('Utilisateur introuvable.');
        }

        return $this->render('moderateur/verifications_show.html.twig', [
            'u' => $u,
            'pp' => $u->getPersonalProfile(),
            'pro' => $u->getProfessionalProfile(),
        ]);
    }

    /**
     * ✅ Afficher la page vérification via slug du PersonalProfile
     * URL: /moderateur/verifications/p/mon-slug
     * Route name: moderateur_verifications_show_slug
     */
    #[Route('/p/{slug}', name: 'show_slug', requirements: ['slug' => '[A-Za-z0-9._-]+'], methods: ['GET'])]
    public function showBySlug(string $slug, UserRepository $userRepo, PersonalProfileRepository $personalProfileRepository): Response
    {
        $personalProfile = $personalProfileRepository->findOneBy(['slug' => $slug]);
        if (!$personalProfile) {
            throw new NotFoundHttpException('Profil introuvable.');
        }

        $u = $personalProfile->getUser();

        if (!$u) {
            throw new NotFoundHttpException('Utilisateur introuvable (slug).');
        }

        return $this->render('moderateur/verifications_show.html.twig', [
            'u' => $u,
            'pp' => $u->getPersonalProfile(),
            'pro' => $u->getProfessionalProfile(),
        ]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(int $id, EntityManagerInterface $em): Response
    {
        $u = $em->getRepository(User::class)->createQueryBuilder('u')
            ->leftJoin('u.personalProfile', 'pp')->addSelect('pp')
            ->leftJoin('u.professionalProfile', 'pro')->addSelect('pro')
            ->andWhere('u.id = :id')->setParameter('id', $id)
            ->getQuery()->getOneOrNullResult();

        if (!$u) throw new NotFoundHttpException('Utilisateur introuvable.');

        return $this->render('moderateur/verifications_show.html.twig', [
            'u' => $u,
            'pp' => $u->getPersonalProfile(),
            'pro' => $u->getProfessionalProfile(),
        ]);
    }

    // ===== CNI actions =====
    #[Route('/{id}/cni/verify', name: 'cni_verify', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function verifyCni(
        int $id,
        Request $request,
        UserRepository $userRepo,
        PersonalProfileRepository $ppRepo,
        MailerInterface $mailer,
        EntityManagerInterface $em,
        ConversationRepository $conversationRepo
    ): Response {
        $this->guardCsrf($request, 'verif_'.$id.'_cni');

        $u = $userRepo->find($id);

        if (!$u || !$u->getPersonalProfile()) {
            throw new NotFoundHttpException('Profil introuvable.');
        }

        $pp = $u->getPersonalProfile();

        $isCompany = in_array('ROLE_COMPANY', $u->getRoles(), true);

        if (!$isCompany && !$pp->getCni()) {
            $this->addFlash('warning', 'Aucun fichier CNI trouvé.');
            return $this->redirectToRoute('moderateur_verifications_show', ['id' => $id]);
        }

        /** @var User $moderateur */
        $moderateur = $this->getUser();

        $ppRepo->markCniStatus($pp->getId(), 'verified', (int) $moderateur->getId());

        // ===== Mail =====
        $mailSubject = $isCompany
            ? 'Validation de votre compte entreprise'
            : 'Validation de votre CNI';

        $mailContent = $isCompany
            ? 'Votre compte entreprise a été vérifié avec succès sur la plateforme.'
            : 'Votre pièce d’identité (CNI) a été vérifiée avec succès sur la plateforme.';

        $this->sendVerificationEmail($u, $mailSubject, $mailContent, $mailer);

        // ===== Message interne =====
        $chatMessage = $isCompany
            ? 'Bonjour, votre compte entreprise a été vérifié avec succès ✅'
            : 'Bonjour, votre CNI a été vérifiée avec succès ✅';

        $this->sendInternalSystemMessage($moderateur, $u, $chatMessage, $conversationRepo, $em);

        $this->addFlash('success', $isCompany ? 'Entreprise vérifiée ✅' : 'CNI vérifiée ✅');

        return $this->redirectToRoute('moderateur_verifications_show', ['id' => $id]);
    }

    #[Route('/{id}/cni/reject', name: 'cni_reject', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function rejectCni(
        int $id,
        Request $request,
        UserRepository $userRepo,
        PersonalProfileRepository $ppRepo,
        MailerInterface $mailer,
        EntityManagerInterface $em,
        ConversationRepository $conversationRepo
    ): Response {
        $this->guardCsrf($request, 'verif_'.$id.'_cni');

        $u = $userRepo->findOneWithProfiles($id);
        if (!$u || !$u->getPersonalProfile()) {
            throw new NotFoundHttpException('Profil introuvable.');
        }

        /** @var User $moderateur */
        $moderateur = $this->getUser();

        $ppRepo->markCniStatus($u->getPersonalProfile()->getId(), 'rejected', (int) $moderateur->getId());

        // ===== Mail =====
        $this->sendVerificationEmail(
            $u,
            'Rejet de votre CNI',
            'Votre pièce d’identité (CNI) a été examinée, mais elle n’a pas pu être validée. Merci de soumettre un document conforme.',
            $mailer
        );

        // ===== Message interne =====
        $this->sendInternalSystemMessage(
            $moderateur,
            $u,
            'Bonjour, votre CNI a été rejetée ⛔. Merci de soumettre un document conforme.',
            $conversationRepo,
            $em
        );

        $this->addFlash('success', 'CNI rejetée ⛔');

        return $this->redirectToRoute('moderateur_verifications_show', ['id' => $id]);
    }

    // ===== CV actions (talent) =====
    #[Route('/{id}/cv/verify', name: 'cv_verify', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function verifyCv(
        int $id,
        Request $request,
        UserRepository $userRepo,
        ProfessionalProfileRepository $proRepo,
        MailerInterface $mailer,
        EntityManagerInterface $em,
        ConversationRepository $conversationRepo
    ): Response {
        $this->guardCsrf($request, 'verif_'.$id.'_cv');

        $u = $userRepo->findOneWithProfiles($id);
        if (!$u || !$u->getProfessionalProfile()) {
            $this->addFlash('warning', 'Ce compte n’a pas de profil professionnel.');
            return $this->redirectToRoute('moderateur_verifications_show', ['id' => $id]);
        }

        $pro = $u->getProfessionalProfile();
        if (!$pro->getCv()) {
            $this->addFlash('warning', 'Aucun fichier CV trouvé.');
            return $this->redirectToRoute('moderateur_verifications_show', ['id' => $id]);
        }

        /** @var User $moderateur */
        $moderateur = $this->getUser();

        $proRepo->markCvVerified($pro->getId(), true, (int) $moderateur->getId());

        // ===== Mail =====
        $this->sendVerificationEmail(
            $u,
            'Validation de votre CV',
            'Votre CV a été vérifié avec succès sur la plateforme.',
            $mailer
        );

        // ===== Message interne =====
        $this->sendInternalSystemMessage(
            $moderateur,
            $u,
            'Bonjour, votre CV a été vérifié avec succès ✅',
            $conversationRepo,
            $em
        );

        $this->addFlash('success', 'CV vérifié ✅');
        return $this->redirectToRoute('moderateur_verifications_show', ['id' => $id]);
    }

    #[Route('/{id}/cv/reject', name: 'cv_reject', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function rejectCv(
        int $id,
        Request $request,
        UserRepository $userRepo,
        ProfessionalProfileRepository $proRepo,
        MailerInterface $mailer,
        EntityManagerInterface $em,
        ConversationRepository $conversationRepo
    ): Response {
        $this->guardCsrf($request, 'verif_'.$id.'_cv');

        $u = $userRepo->findOneWithProfiles($id);
        if (!$u || !$u->getProfessionalProfile()) {
            throw new NotFoundHttpException('Profil pro introuvable.');
        }

        /** @var User $moderateur */
        $moderateur = $this->getUser();

        $proRepo->markCvVerified($u->getProfessionalProfile()->getId(), false, (int) $moderateur->getId());

        // ===== Mail =====
        $this->sendVerificationEmail(
            $u,
            'Rejet de votre CV',
            'Votre CV a été examiné, mais il n’a pas pu être validé. Merci de téléverser un document conforme.',
            $mailer
        );

        // ===== Message interne =====
        $this->sendInternalSystemMessage(
            $moderateur,
            $u,
            'Bonjour, votre CV a été rejeté ⛔. Merci de téléverser un document conforme.',
            $conversationRepo,
            $em
        );

        $this->addFlash('success', 'CV rejeté ⛔');

        return $this->redirectToRoute('moderateur_verifications_show', ['id' => $id]);
    }

    // ===== Valider profil (objectif 100%) =====
    #[Route('/{id}/validate', name: 'validate', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function validateProfile(
        int $id,
        Request $request,
        UserRepository $userRepo,
        PersonalProfileRepository $ppRepo
    ): Response {
        
        $this->guardCsrf($request, 'verif_'.$id.'_validate');

        $u = $userRepo->findOneWithProfiles($id);
        if (!$u) throw new NotFoundHttpException('Utilisateur introuvable.');

        $pp  = $u->getPersonalProfile();
        $pro = $u->getProfessionalProfile();

        if (!$pp) {
            $this->addFlash('warning', 'Impossible : profil personnel introuvable.');
            return $this->redirectToRoute('moderateur_verifications_show', ['id' => $id]);
        }

        $isTalent  = in_array('ROLE_TALENT', $u->getRoles(), true);
        $isCompany = in_array('ROLE_COMPANY', $u->getRoles(), true);

        // ✅ Company : pas de doc => validation directe via cniStatus = verified (statut global)
        if ($isCompany) {
            /**
             * @var User
             */
            $user = $this->getUser();
            $ppRepo->markCniStatus($pp->getId(), 'verified', (int) $user->getId());
            $this->addFlash('success', 'Entreprise validée ✅ (dashboard = 100%)');
            return $this->redirectToRoute('moderateur_verifications_show', ['id' => $id]);
        }

        // ✅ Particulier/Talent : CNI obligatoire
        if ($pp->getCniStatus() !== 'verified') {
            $this->addFlash('warning', 'Impossible : la CNI n’est pas vérifiée.');
            return $this->redirectToRoute('moderateur_verifications_show', ['id' => $id]);
        }

        // ✅ Talent : CV obligatoire
        if ($isTalent && (!$pro || !$pro->isVerified())) {
            $this->addFlash('warning', 'Impossible : le CV n’est pas vérifié.');
            return $this->redirectToRoute('moderateur_verifications_show', ['id' => $id]);
        }

        $this->addFlash('success', 'Profil validé ✅ (dashboard = 100%)');
        return $this->redirectToRoute('moderateur_verifications_show', ['id' => $id]);
    }

    // ===== Block / Unblock =====
    #[Route('/{id}/block', name: 'block', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function block(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $this->guardCsrf($request, 'verif_'.$id.'_block');

        $u = $em->getRepository(User::class)->find($id);
        if (!$u) throw new NotFoundHttpException('Utilisateur introuvable.');

        $u->setIsActive(false);
        $em->flush();

        $this->addFlash('success', 'Utilisateur bloqué.');
        return $this->redirectToRoute('moderateur_verifications_index');
    }

    #[Route('/{id}/unblock', name: 'unblock', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function unblock(int $id, Request $request, EntityManagerInterface $em): Response
    {
        $this->guardCsrf($request, 'verif_'.$id.'_block');

        $u = $em->getRepository(User::class)->find($id);
        if (!$u) throw new NotFoundHttpException('Utilisateur introuvable.');

        $u->setIsActive(true);
        $em->flush();

        $this->addFlash('success', 'Utilisateur débloqué.');
        return $this->redirectToRoute('moderateur_verifications_index');
    }

    // ===== Download docs =====
    #[Route('/{id}/download/{type}', name: 'download', requirements: ['id' => '\d+', 'type' => 'cni|cv'], methods: ['GET'])]
    public function download(int $id, string $type, Request $request, EntityManagerInterface $em): Response
    {
        $inline = (bool) $request->query->get('inline', false);

        $u = $em->getRepository(User::class)->createQueryBuilder('u')
            ->leftJoin('u.personalProfile', 'pp')->addSelect('pp')
            ->leftJoin('u.professionalProfile', 'pro')->addSelect('pro')
            ->andWhere('u.id = :id')->setParameter('id', $id)
            ->getQuery()->getOneOrNullResult();

        if (!$u) throw new NotFoundHttpException('Utilisateur introuvable.');

        $file = null;
        $dir  = null;

        if ($type === 'cni') {
            $pp = $u->getPersonalProfile();
            $file = $pp?->getCni();
            $dir = $this->getParameter('cni_upload_dir');
        } else { // cv
            $pro = $u->getProfessionalProfile();
            $file = $pro?->getCv();
            $dir = $this->getParameter('cv_upload_dir');
        }

        if (!$file || !$dir) throw new NotFoundHttpException('Fichier introuvable.');

        $path = rtrim((string)$dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file;
        if (!is_file($path)) throw new NotFoundHttpException('Fichier introuvable sur le serveur.');

        $response = new BinaryFileResponse($path);
        $disposition = $inline ? ResponseHeaderBag::DISPOSITION_INLINE : ResponseHeaderBag::DISPOSITION_ATTACHMENT;
        $response->setContentDisposition($disposition, basename($file));

        return $response;
    }
    private function guardCsrf(Request $request, string $tokenId): void
    {
        if (!$this->isCsrfTokenValid($tokenId, (string) $request->request->get('_token'))) {
            throw new NotFoundHttpException('CSRF invalide.');
        }
    }

    private function computeStats(EntityManagerInterface $em): array
    {
        // stats rapides (CNI)
        $cni = $em->createQueryBuilder()
            ->select('pp.cniStatus as st, COUNT(pp.id) as c')
            ->from(PersonalProfile::class, 'pp')
            ->groupBy('pp.cniStatus')
            ->getQuery()->getArrayResult();

        $cniMap = ['pending'=>0,'verified'=>0,'rejected'=>0];
        foreach ($cni as $row) $cniMap[$row['st']] = (int)$row['c'];

        // stats rapides (CV) = talents seulement
        $cvRows = $em->createQueryBuilder()
            ->select('COUNT(pro.id) as total,
                      SUM(CASE WHEN pro.isVerified = true THEN 1 ELSE 0 END) as verified,
                      SUM(CASE WHEN pro.isVerified = false AND pro.verifiedAt IS NULL THEN 1 ELSE 0 END) as pending,
                      SUM(CASE WHEN pro.isVerified = false AND pro.verifiedAt IS NOT NULL THEN 1 ELSE 0 END) as rejected')
            ->from(ProfessionalProfile::class, 'pro')
            ->getQuery()->getOneOrNullResult();

        return [
            'cni' => $cniMap,
            'cv'  => [
                'pending'  => (int)($cvRows['pending'] ?? 0),
                'verified' => (int)($cvRows['verified'] ?? 0),
                'rejected' => (int)($cvRows['rejected'] ?? 0),
            ]
        ];
    }


    private function sendVerificationEmail(
        User $recipient,
        string $subject,
        string $content,
        MailerInterface $mailer
    ): void {
        if (!$recipient->getEmail()) {
            return;
        }

        $email = (new Email())
            ->from('no-reply@tonsite.com') // remplace par ton adresse
            ->to($recipient->getEmail())
            ->subject($subject)
            ->html("
                <div style='font-family: Arial, sans-serif; font-size: 14px; color: #222;'>
                    <p>Bonjour,</p>
                    <p>{$content}</p>
                    <p>Cordialement,<br>L'équipe</p>
                </div>
            ");

        $mailer->send($email);
    }

    private function sendInternalSystemMessage(
        User $sender,
        User $recipient,
        string $content,
        ConversationRepository $conversationRepo,
        EntityManagerInterface $em
    ): void {
        $senderId = $sender->getId();
        $recipientId = $recipient->getId();

        if (!$senderId || !$recipientId) {
            return;
        }

        // toujours ranger les participants dans l'ordre croissant
        $userA = $senderId < $recipientId ? $sender : $recipient;
        $userB = $senderId < $recipientId ? $recipient : $sender;

        $conversation = $conversationRepo->findOneBy([
            'participantA' => $userA,
            'participantB' => $userB,
        ]);

        if (!$conversation) {
            $conversation = new Conversation();
            $conversation->setParticipantA($userA);
            $conversation->setParticipantB($userB);
            $conversation->setCreatedAt(new \DateTimeImmutable());

            $em->persist($conversation);
        }

        $message = new Message();
        $message->setConversation($conversation);
        $message->setSender($sender); // ici le modérateur
        $message->setType(Message::TYPE_SYSTEM);
        $message->setContent($content);

        $conversation->addMessage($message);

        $em->persist($message);
        $em->persist($conversation);
        $em->flush();
    }
}