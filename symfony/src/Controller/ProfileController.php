<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\PasswordForm;
use App\Form\ProfileForm;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class ProfileController extends AbstractController
{
    #[Route('/profile/theme', name: 'app_profile_theme', methods: ['POST'])]
    public function theme(Request $request, #[CurrentUser] User $user, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('theme_preference', $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton de sécurité invalide.');
        }
        $theme = $request->getPayload()->getString('theme');
        if (!in_array($theme, ['dark', 'light'], true)) {
            return $this->json(['error' => 'Choisissez un thème clair ou sombre.'], Response::HTTP_BAD_REQUEST);
        }
        $user->setTheme($theme);
        $em->flush();

        if ($request->getPreferredFormat() === 'json') {
            return $this->json(['theme' => $theme]);
        }

        return $this->redirectToRoute('app_profile', status: Response::HTTP_SEE_OTHER);
    }

    #[Route('/profile', name: 'app_profile', methods: ['GET', 'POST'])]
    public function profile(Request $request, #[CurrentUser] User $user, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ProfileForm::class, $user);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Profil mis à jour.');

            return $this->redirectToRoute('app_profile', status: Response::HTTP_SEE_OTHER);
        }

        return $this->render('profile/index.html.twig', ['form' => $form]);
    }

    #[Route('/profile/password', name: 'app_profile_password', methods: ['GET', 'POST'])]
    public function password(Request $request, #[CurrentUser] User $user, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        $form = $this->createForm(PasswordForm::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $user->setPassword($hasher->hashPassword($user, $form->get('plainPassword')->getData()));
            $em->flush();
            $this->addFlash('success', 'Mot de passe modifié.');

            return $this->redirectToRoute('app_profile', status: Response::HTTP_SEE_OTHER);
        }

        return $this->render('profile/password.html.twig', ['form' => $form]);
    }
}
