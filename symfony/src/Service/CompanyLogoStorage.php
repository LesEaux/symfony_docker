<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class CompanyLogoStorage
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/var/company_logos')] private string $directory,
        private Filesystem $filesystem,
        private LoggerInterface $logger,
    ) {
    }

    public function store(UploadedFile $file): string
    {
        $extension = match ($file->getMimeType()) {
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
            default => throw new \InvalidArgumentException('Format de logo invalide.'),
        };
        $filename = bin2hex(random_bytes(16)).'.'.$extension;
        $this->filesystem->mkdir($this->directory);
        $file->move($this->directory, $filename);

        return $filename;
    }

    public function getPath(string $filename): string
    {
        if (!preg_match('/^[a-f0-9]{32}\.(png|jpg|webp)$/D', $filename)) {
            throw new \InvalidArgumentException('Nom de fichier invalide.');
        }

        return $this->directory.'/'.$filename;
    }

    public function delete(?string $filename): void
    {
        if ($filename === null) {
            return;
        }
        try {
            $this->filesystem->remove($this->getPath($filename));
        } catch (IOExceptionInterface $error) {
            $this->logger->warning('Unable to remove a superseded company logo.', ['exception' => $error]);
        }
    }
}
