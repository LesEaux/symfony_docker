<?php

namespace App\Service;

use App\Entity\CompanySettings;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class CompanyBranding
{
    private ?CompanySettings $settings = null;
    private bool $loaded = false;

    public function __construct(private EntityManagerInterface $em, private UrlGeneratorInterface $urls)
    {
    }

    public function getSettings(): ?CompanySettings
    {
        if (!$this->loaded) {
            $this->settings = $this->em->find(CompanySettings::class, 1);
            $this->loaded = true;
        }

        return $this->settings;
    }

    public function getLogoUrl(): ?string
    {
        $filename = $this->getSettings()?->getLogoFilename();

        return $filename === null ? null : $this->urls->generate('app_company_logo', ['filename' => $filename]);
    }
}
