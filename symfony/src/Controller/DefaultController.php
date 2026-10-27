<?php

// src/Controller/DefaultController.php

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class DefaultController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(UserRepository $users): Response
    {
        $authenticated = $this->isGranted('ROLE_USER');

        return $this->render('default/index.html.twig', [
            'stats' => $authenticated ? $users->getDashboardStats() : null,
            'latest_users' => $authenticated ? $users->findBy([], ['id' => 'DESC'], 5) : [],
        ]);
    }
}
