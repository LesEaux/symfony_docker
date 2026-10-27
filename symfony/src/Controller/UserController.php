<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserForm;
use App\Repository\UserRepository;
use App\Service\AccountManager;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/user')]
#[IsGranted('ROLE_USER')]
final class UserController extends AbstractController
{
    #[Route(name: 'app_user_index', methods: ['GET'])]
    public function index(
        UserRepository $userRepository,
        PaginatorInterface $paginator,
        Request $request,
    ): Response {
        // 1) Construire un QueryBuilder de base (trié par email, par exemple)
        $qb = $userRepository->createQueryBuilder('u')
            ->orderBy('u.email', 'ASC');

        if (!in_array($request->query->get('sort', 'u.email'), ['u.email', 'u.nom', 'u.prenom'], true)) {
            $request->query->set('sort', 'u.email');
        }

        // 2) Récupérer les valeurs des champs de recherche depuis l'URL (GET)
        $email = $request->query->get('email', '');
        $nom = $request->query->get('nom', '');
        $prenom = $request->query->get('prenom', '');
        $role = $request->query->get('role', '');

        // 3) Appliquer un filtre “LIKE” si le champ n'est pas vide
        if ($email !== '') {
            $qb->andWhere('u.email LIKE :email')
               ->setParameter('email', '%'.$email.'%');
        }
        if ($nom !== '') {
            $qb->andWhere('u.nom LIKE :nom')
               ->setParameter('nom', '%'.$nom.'%');
        }
        if ($prenom !== '') {
            $qb->andWhere('u.prenom LIKE :prenom')
               ->setParameter('prenom', '%'.$prenom.'%');
        }
        if ($role !== '') {
            // Comme “roles” est un champ stockant un tableau JSON, on fait un LIKE sur la chaîne JSON
            // pour rechercher par exemple “ROLE_ADMIN” ou “ROLE_USER” dans l’array.
            $qb->andWhere('u.roles LIKE :role')
               ->setParameter('role', '%"'.$role.'"%');
        }

        // 4) Paginer le résultat
        $page = max(1, $request->query->getInt('page', 1));
        $pagination = $paginator->paginate(
            $qb,    // la QueryBuilder
            $page,  // page demandée (GET “page”)
            10      // 10 utilisateurs par page
        );

        // 5) Renvoyer à Twig
        return $this->render('user/index.html.twig', [
            'pagination' => $pagination,
        ]);
    }

    #[Route('/new', name: 'app_user_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function new(Request $request, EntityManagerInterface $entityManager, UserPasswordHasherInterface $hasher): Response
    {
        $user = new User();
        $form = $this->createForm(UserForm::class, $user, ['password_required' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // 1) récupère le mot de passe “en clair”
            $plain = $form->get('plainPassword')->getData();

            // 2) hache-le
            $hashed = $hasher->hashPassword($user, $plain);
            $user->setPassword($hashed);

            // 3) gérer le rôle choisi
            $role = $form->get('roles')->getData(); // ex. 'ROLE_UTILISATEUR'
            $user->setRoles([$role]);

            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'Utilisateur créé.');

            return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('user/new.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_user_show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(User $user): Response
    {
        return $this->render('user/show.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_user_edit', requirements: ['id' => '\\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, User $user, AccountManager $accounts, UserPasswordHasherInterface $hasher): Response
    {
        $form = $this->createForm(UserForm::class, $user);
        // Initialiser le champ “role” à partir de l’entité
        // (prendre le premier rôle ou null si aucun)
        if ($existing = $user->getRoles()) {
            $form->get('roles')->setData($existing[0]);
        }

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Même logique : on écrase
            $role = $form->get('roles')->getData();
            $user->setRoles([$role]);

            $plain = $form->get('plainPassword')->getData();
            if ($plain !== null && $plain !== '') {
                $user->setPassword($hasher->hashPassword($user, $plain));
            }

            try {
                $accounts->save($user);
                $this->addFlash('success', 'Utilisateur modifié.');

                return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
            } catch (\DomainException $error) {
                $form->addError(new FormError($error->getMessage()));
            }
        }

        return $this->render('user/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_user_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, User $user, AccountManager $accounts): Response
    {
        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->getPayload()->getString('_token'))) {
            try {
                $accounts->delete($user);
                $this->addFlash('success', 'Utilisateur supprimé.');
            } catch (\DomainException $error) {
                $this->addFlash('danger', $error->getMessage());
            }
        } else {
            throw $this->createAccessDeniedException('Jeton de sécurité invalide.');
        }

        return $this->redirectToRoute('app_user_index', [], Response::HTTP_SEE_OTHER);
    }
}
