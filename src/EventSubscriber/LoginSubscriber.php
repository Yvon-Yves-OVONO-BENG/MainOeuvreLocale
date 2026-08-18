<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Entity\UserLog;
use App\Repository\BrowserRepository;
use App\Repository\DeviceTypeRepository;
use App\Repository\OperatingSystemRepository;
use App\Repository\UserRepository;
use App\Service\UserLogService;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Jenssegers\Agent\Agent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

class LoginSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private RequestStack $requestStack,
        private UserLogService $geoService,
        private DeviceTypeRepository $deviceRepo,
        private OperatingSystemRepository $osRepo,
        private BrowserRepository $browserRepo,
        private UserRepository $userRepository,
    ) {}

    public function onLoginSuccess(InteractiveLoginEvent $event)
    {
        $user = $event->getAuthenticationToken()->getUser();
        $request = $this->requestStack->getCurrentRequest();
        $mySession = $request->getSession();

        $ip = $request->getClientIp();
        $userAgent = $request->headers->get('User-Agent');

        $agent = new Agent();
        $agent->setUserAgent($userAgent);
        
        // 🔍 Récupération Device, OS, Browser
        $deviceName = $agent->deviceType(); // ex : mobile, desktop
        $osName = $agent->platform();       // ex : Android, Windows
        $browserName = $agent->browser();   // ex : Chrome, Safari
        
        $device = $this->deviceRepo->findOneBy(['deviceType' => $deviceName]);
        $os = $this->osRepo->findOneBy(['operatingSystem' => $osName]);
        $browser = $this->browserRepo->findOneBy(['browser' => $browserName]);

        // 📍 Géolocalisation
        $geo = $this->geoService->getCityAndCountry($ip);
        
        $ville = $geo['city'] ?? null;
        
        /** @var \App\Entity\Country|null $countryEntity */
        $countryEntity = $geo['countri'] ?? null;
        
        // (optionnel) label string pour session/affichage
        $countryLabel = $geo['country_label'] ?? null;
        
        $mySession->set('ville', $ville);
        $mySession->set('pays', $countryLabel);
        

        // 💾 Création du log
        $log = new UserLog();
        $log->setUser($user)
            ->setIp($ip)
            ->setUserAgent($userAgent)
            ->setDeviceType($device)
            ->setOperatingSystem($os)
            ->setBrowser($browser)
            ->setAction('login')
            ->setVille($ville ?: "unknow")
            ->setCountry($countryEntity)   // ✅ IMPORTANT: même variable
            ->setLogedAt(new \DateTime())
            ->setLastSeenAt(new DateTimeImmutable());

        $user = $this->userRepository->findOneBy(['email' => $user->getUserIdentifier()]);
        $user->setIsOnline(true);
        $user->setLastSeenAt(new \DateTimeImmutable());
       
        
        $this->em->persist($user);
        $this->em->persist($log);
        $this->em->flush();
        $mySession->set('active_userlog_id', $log->getId());

    }

     public static function getSubscribedEvents(): array
    {
        return [
            InteractiveLoginEvent::class => 'onLoginSuccess',
        ];
    }
}