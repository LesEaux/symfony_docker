<?php

namespace App\Controller;

use App\Form\PasswordForm;
use App\Service\PasswordResetter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;

final class PasswordResetController extends AbstractController
{
    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function requestReset(Request $request, PasswordResetter $resetter, #[Autowire(service: 'limiter.password_reset')] RateLimiterFactory $limiter): Response
    {
        $form = $this->createFormBuilder()->add('email', EmailType::class, [
            'label' => 'Email', 'constraints' => [new Assert\NotBlank(), new Assert\Email(), new Assert\Length(max: 180)],
        ])->getForm();
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($limiter->create($request->getClientIp() ?? 'unknown')->consume()->isAccepted()) {
                $resetter->request($form->get('email')->getData());
            }
            $this->addFlash('success', 'Si un compte actif correspond à cette adresse, un email contenant les instructions vous sera envoyé.');

            return $this->redirectToRoute('app_login', status: Response::HTTP_SEE_OTHER);
        }

        return $this->render('security/forgot_password.html.twig', ['form' => $form]);
    }

    #[Route('/reset-password/{token}', name: 'app_reset_password', requirements: ['token' => '[a-f0-9]{64}'], methods: ['GET', 'POST'])]
    public function reset(Request $request, string $token, PasswordResetter $resetter): Response
    {
        if (!$resetter->find($token)) {
            $this->addFlash('warning', 'Ce lien est invalide ou a expiré. Demandez un nouveau lien.');

            return $this->redirectToRoute('app_forgot_password');
        }
        $form = $this->createForm(PasswordForm::class, options: ['require_current' => false]);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            if ($resetter->reset($token, $form->get('plainPassword')->getData())) {
                $this->addFlash('success', 'Mot de passe réinitialisé. Vous pouvez vous connecter.');

                return $this->redirectToRoute('app_login', status: Response::HTTP_SEE_OTHER);
            }

            return $this->redirectToRoute('app_forgot_password');
        }
        $response = $this->render('security/reset_password.html.twig', ['form' => $form]);
        $response->headers->set('Referrer-Policy', 'no-referrer');
        $response->headers->set('Cache-Control', 'no-store, private');

        return $response;
    }
}
