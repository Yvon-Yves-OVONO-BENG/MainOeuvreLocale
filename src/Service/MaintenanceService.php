<?php

namespace App\Service;

use App\Entity\PlatformSetting;
use App\Repository\PlatformSettingRepository;
use Doctrine\ORM\EntityManagerInterface;

class MaintenanceService
{
    public function __construct(
        private readonly PlatformSettingRepository $platformSettingRepository,
        private readonly EntityManagerInterface $em,
    ) {
    }

    public function getSetting(): PlatformSetting
    {
        $setting = $this->platformSettingRepository->getMain();

        if (!$setting) {
            $setting = new PlatformSetting();
            $setting->setMaintenanceEnabled(false);
            $setting->setMaintenanceTitle('Maintenance en cours');
            $setting->setMaintenanceMessage('Nous revenons très bientôt.');

            $this->em->persist($setting);
            $this->em->flush();
        }

        return $setting;
    }

    public function isEnabled(): bool
    {
        return $this->getSetting()->isMaintenanceEnabled();
    }

    public function toggle(): bool
    {
        $setting = $this->getSetting();
        $setting->setMaintenanceEnabled(!$setting->isMaintenanceEnabled());
        $setting->setUpdatedAt(new \DateTime());

        $this->em->flush();

        return $setting->isMaintenanceEnabled();
    }
}