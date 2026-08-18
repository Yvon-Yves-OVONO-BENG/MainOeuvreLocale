<?php

namespace App\Twig;

use App\Entity\User;
use App\Repository\NewsletterSubscriberRepository;
use App\Repository\ProfessionRepository;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class OfferAlertExtension extends AbstractExtension
{
    public function __construct(
        // Conservé pour rester compatible avec le conteneur Symfony déjà
        // compilé par la version précédente de l'extension. La profession
        // n'est plus demandée dans le formulaire d'alerte.
        private readonly ProfessionRepository $professionRepository,
        private readonly NewsletterSubscriberRepository $subscriberRepository,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('offer_alert_footer_data', [$this, 'getFooterData']),
        ];
    }

    /**
     * @return array{subscription: mixed}
     */
    public function getFooterData(?User $user): array
    {
        $subscription = null;

        if ($user) {
            try {
                $subscription = $this->subscriberRepository->findOneByEmail($user->getEmail());
            } catch (\Throwable) {
                // Le footer reste utilisable avant l'exécution de la migration.
            }
        }

        return [
            'subscription' => $subscription,
        ];
    }
}
