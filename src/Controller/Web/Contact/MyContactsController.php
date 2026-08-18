<?php

namespace App\Controller\Web\Contact;

use App\Entity\User;
use App\Entity\Message;
use App\Entity\Conversation;
use App\Repository\MessageRepository;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\ConversationRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

class MyContactsController extends AbstractController
{
    #[Route('/contacts', name: 'contacts_index', methods: ['GET'])]
    public function index(
        Request $request,
        ConversationRepository $conversationRepo,
        MessageRepository $messageRepo,
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_USER');

        /** @var User|null $me */
        $me = $this->getUser();
        if (!$me instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $page  = max(1, (int) $request->query->get('page', 1));
        $limit = 12;

        // ✅ Conversations = Contacts (grâce à ton modèle)
        $totalContacts = $conversationRepo->countContactsForUser($me);
        $conversations = $conversationRepo->findContactsForUserPaginated($me, $page, $limit);

        $conversationIds = array_values(array_filter(array_map(
            static fn (Conversation $c) => $c->getId(),
            $conversations
        )));

        // ✅ Données de confort UX (sans N+1)
        $unreadCounts = $messageRepo->getUnreadCountsByConversationIdsForUser($conversationIds, $me);
        $lastMessages = $messageRepo->getLastMessagesByConversationIds($conversationIds);

        // ✅ Préparer des "cards" prêtes pour Twig
        $cards = [];
        foreach ($conversations as $conversation) {
            $other = $conversation->getOtherParticipant($me);
            if (!$other instanceof User) {
                continue;
            }

            $convId = (int) $conversation->getId();
            $last   = $lastMessages[$convId] ?? null;

            $preview = null;
            if ($last instanceof Message) {
                if ($last->isDeleted()) {
                    $preview = '🗑️ Message supprimé';
                } elseif ($last->getType() === Message::TYPE_FILE) {
                    $preview = '📎 Fichier';
                } elseif ($last->getType() === Message::TYPE_CALL) {
                    $preview = '📞 Appel';
                } elseif ($last->getType() === Message::TYPE_SYSTEM) {
                    $preview = 'ℹ️ Notification système';
                } else {
                    $preview = trim((string) $last->getContent());
                }
            }

            $cards[] = [
                'conversation'   => $conversation,
                'other'          => $other,
                'professionalProfile' => $conversation->getProfessionalProfile(),
                'unreadCount'    => $unreadCounts[$convId] ?? 0,
                'lastMessage'    => $last,
                'preview'        => $preview,
                'lastAt'         => $conversation->getLastMessageAt() ?? $conversation->getUpdatedAt() ?? $conversation->getCreatedAt(),
            ];
        }

        $totalPages = max(1, (int) ceil($totalContacts / $limit));

        // ✅ Petites stats UX
        $contactsWithUnread = $conversationRepo->countContactsWithUnread($me);
        $recentActiveCount = 0;
        foreach ($cards as $card) {
            $dt = $card['lastAt'];
            if ($dt instanceof \DateTimeInterface && $dt >= (new \DateTimeImmutable('-7 days'))) {
                $recentActiveCount++;
            }
        }

        return $this->render('mes_contacts/mes_contacts.html.twig', [
            'contacts' => $cards,
            'pagination' => [
                'page'       => $page,
                'limit'      => $limit,
                'total'      => $totalContacts,
                'totalPages' => $totalPages,
            ],
            'stats' => [
                'totalContacts'      => $totalContacts,
                'contactsWithUnread' => $contactsWithUnread,
                'recentActiveCount'  => $recentActiveCount,
            ],
        ]);
    }
}