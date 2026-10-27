<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class CompanySettings
{
    #[ORM\Id]
    #[ORM\Column]
    private int $id = 1;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logoFilename = null;

    public function getId(): int
    {
        return $this->id;
    }

    public function getLogoFilename(): ?string
    {
        return $this->logoFilename;
    }

    public function setLogoFilename(?string $filename): static
    {
        $this->logoFilename = $filename;

        return $this;
    }
}
