<?php

declare(strict_types=1);

namespace App\Controller\Web\Annonce;

use App\Entity\Annonce;
use App\Entity\BoosteAnnonce;
use App\Entity\User;
use App\Repository\AnnonceRepository;
use App\Repository\BoosteAnnonceRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/annonces')]
#[IsGranted('ROLE_USER')]
final class AnnonceBoostController extends AbstractController
{
    /**
     * @var array<string, array{
     *     name: string,
     *     price: int,
     *     weeks: int,
     *     priority: int,
     *     renewable: bool,
     *     icon: string,
     *     class: string,
     *     description: string
     * }>
     */
    private const BOOST_PLANS = [
        'essentiel' => [
            'name' => 'Essentiel',
            'price' => 200,
            'weeks' => 1,
            'priority' => 1,
            'renewable' => true,
            'icon' => '🟢',
            'class' => 'essential',
            'description' => '1 semaine de visibilité renforcée.',
        ],
        'plus' => [
            'name' => 'Plus',
            'price' => 500,
            'weeks' => 3,
            'priority' => 2,
            'renewable' => true,
            'icon' => '⭐',
            'class' => 'plus',
            'description' => '3 semaines de visibilité prioritaire.',
        ],
        'premium' => [
            'name' => 'Premium',
            'price' => 1000,
            'weeks' => 8,
            'priority' => 3,
            'renewable' => false,
            'icon' => '👑',
            'class' => 'premium',
            'description' => '8 semaines de visibilité prioritaire.',
        ],
    ];

    #[Route('/booster', name: 'annonce_boost', methods: ['GET'])]
    public function index(
        Request $request,
        AnnonceRepository $annonceRepository,
        BoosteAnnonceRepository $boostRepository,
    ): Response {
        $user = $this->requireUser();
        $annonces = $annonceRepository->findForUser($user);

        $selectedAnnonce = $this->resolveOwnedAnnonce(
            (string) $request->query->get('annonce', ''),
            $user,
            $annonceRepository,
        );

        return $this->render('annonce/boost.html.twig', [
            'annonces' => $annonces,
            'selectedAnnonce' => $selectedAnnonce,
            'plans' => self::BOOST_PLANS,
            'recentBoosts' => $selectedAnnonce instanceof Annonce
                ? $boostRepository->findRecentForAnnonce($selectedAnnonce, $user)
                : [],
        ]);
    }

    #[Route(
        '/booster/{plan}',
        name: 'annonce_boost_select',
        methods: ['POST'],
        requirements: ['plan' => 'essentiel|plus|premium'],
    )]
    public function select(
        string $plan,
        Request $request,
        AnnonceRepository $annonceRepository,
        BoosteAnnonceRepository $boostRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->requireUser();

        $annonce = $this->resolveOwnedAnnonce(
            (string) $request->request->get('annonce', ''),
            $user,
            $annonceRepository,
        );

        if (!$annonce instanceof Annonce) {
            $this->addFlash('annonce_error', 'Sélectionnez une annonce à booster.');

            return $this->redirectToRoute('annonce_boost');
        }

        $this->assertValidCsrfToken($annonce, $request);

        $planData = $this->getPlan($plan);

        $boost = $boostRepository->findPendingForAnnonce($annonce, $user)
            ?? $this->createBoost($annonce, $user);

        $this->configureBoost($boost, $plan, $planData);

        $entityManager->persist($boost);
        $entityManager->flush();

        $this->addFlash(
            'annonce_success',
            sprintf(
                'Boost %s sélectionné pour « %s » (%s FCFA). La demande est en attente de paiement.',
                $planData['name'],
                $annonce->getTitre(),
                number_format($planData['price'], 0, ',', ' '),
            ),
        );

        return $this->redirectToRoute('annonce_boost', [
            'annonce' => $annonce->getSlug(),
        ]);
    }

    private function createBoost(Annonce $annonce, User $user): BoosteAnnonce
    {
        return (new BoosteAnnonce())
            ->setAnnonce($annonce)
            ->setUser($user);
    }

    /**
     * @param array{
     *     name: string,
     *     price: int,
     *     weeks: int,
     *     priority: int,
     *     renewable: bool,
     *     icon: string,
     *     class: string,
     *     description: string
     * } $planData
     */
    private function configureBoost(
        BoosteAnnonce $boost,
        string $plan,
        array $planData,
    ): void {
        $boost
            ->setPlan($plan)
            ->setMontant($planData['price'])
            ->setDureeSemaines($planData['weeks'])
            ->setNiveauPriorite($planData['priority'])
            ->setRenouvelable($planData['renewable'])
            ->setStatut(BoosteAnnonce::STATUS_PENDING_PAYMENT)
            ->setDateDemande(new DateTimeImmutable())
            ->setDateDebut(null)
            ->setDateFin(null);
    }

    private function assertValidCsrfToken(Annonce $annonce, Request $request): void
    {
        $tokenId = 'annonce_boost_' . $annonce->getId();
        $submittedToken = (string) $request->request->get('_token');

        if (!$this->isCsrfTokenValid($tokenId, $submittedToken)) {
            throw $this->createAccessDeniedException('Jeton de sécurité invalide.');
        }
    }

    /**
     * @return array{
     *     name: string,
     *     price: int,
     *     weeks: int,
     *     priority: int,
     *     renewable: bool,
     *     icon: string,
     *     class: string,
     *     description: string
     * }
     */
    private function getPlan(string $plan): array
    {
        $planData = self::BOOST_PLANS[$plan] ?? null;

        if ($planData === null) {
            throw $this->createNotFoundException('Offre de boost introuvable.');
        }

        return $planData;
    }

    private function resolveOwnedAnnonce(
        string $slug,
        User $user,
        AnnonceRepository $annonceRepository,
    ): ?Annonce {
        if (preg_match('/^[a-f0-9]{64}$/', $slug) !== 1) {
            return null;
        }

        $annonce = $annonceRepository->findOneBy(['slug' => $slug]);

        if (!$annonce instanceof Annonce) {
            return null;
        }

        return $annonce->getUser()?->getId() === $user->getId()
            ? $annonce
            : null;
    }

    private function requireUser(): User
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        return $user;
    }
}
