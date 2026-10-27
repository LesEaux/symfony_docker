<?php

namespace App\Controller;

use App\Entity\CompanySettings;
use App\Form\CompanyLogoForm;
use App\Service\CompanyBranding;
use App\Service\CompanyLogoStorage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class CompanyController extends AbstractController
{
    #[Route('/admin/company', name: 'app_company_settings', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function settings(Request $request, CompanyBranding $branding, CompanyLogoStorage $storage, EntityManagerInterface $em): Response
    {
        $settings = $branding->getSettings() ?? new CompanySettings();
        $form = $this->createForm(CompanyLogoForm::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $upload = $form->get('logo')->getData();
            $remove = $form->get('removeLogo')->getData();
            if ($upload instanceof UploadedFile && $remove) {
                $form->addError(new FormError('Choisissez un nouveau logo ou sa suppression, pas les deux.'));
            } else {
                $previous = $settings->getLogoFilename();
                $filename = $previous;
                $stored = true;
                try {
                    if ($upload instanceof UploadedFile) {
                        $filename = $storage->store($upload);
                    } elseif ($remove) {
                        $filename = null;
                    }
                } catch (FileException|IOExceptionInterface) {
                    $stored = false;
                    $form->get('logo')->addError(new FormError('Le logo n’a pas pu être enregistré. Réessayez.'));
                }
                if ($stored) {
                    $settings->setLogoFilename($filename);
                    $em->persist($settings);
                    try {
                        $em->flush();
                    } catch (\Throwable $error) {
                        if ($filename !== $previous) {
                            $storage->delete($filename);
                        }
                        throw $error;
                    }
                    if ($filename !== $previous) {
                        $storage->delete($previous);
                    }
                    $this->addFlash('success', 'Logo de l’entreprise mis à jour.');

                    return $this->redirectToRoute('app_company_settings', status: Response::HTTP_SEE_OTHER);
                }
            }
        }

        return $this->render('company/settings.html.twig', ['form' => $form]);
    }

    #[Route('/company/logo/{filename}', name: 'app_company_logo', requirements: ['filename' => '[a-f0-9]{32}\.(?:png|jpg|webp)'], methods: ['GET', 'HEAD'])]
    public function logo(string $filename, CompanyBranding $branding, CompanyLogoStorage $storage, Request $request): Response
    {
        if ($branding->getSettings()?->getLogoFilename() !== $filename || !is_file($storage->getPath($filename))) {
            throw $this->createNotFoundException();
        }
        $mime = match (pathinfo($filename, PATHINFO_EXTENSION)) {
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => throw $this->createNotFoundException(),
        };
        $response = new BinaryFileResponse($storage->getPath($filename));
        $response->headers->set('Content-Type', $mime);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->setPublic()->setMaxAge(31536000)->setImmutable();
        $response->isNotModified($request);

        return $response;
    }
}
