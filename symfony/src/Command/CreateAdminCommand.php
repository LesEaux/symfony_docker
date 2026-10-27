<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(name: 'app:create-admin', description: 'Créer un administrateur avec un mot de passe saisi en privé.')]
final class CreateAdminCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $users,
        private UserPasswordHasherInterface $hasher,
        private ValidatorInterface $validator,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if (!$input->isInteractive()) {
            $io->error('Cette commande nécessite une saisie interactive.');

            return Command::INVALID;
        }
        $email = $io->ask('Email');
        if (!$email || $this->users->findOneBy(['email' => $email])) {
            $io->error('Email absent ou déjà utilisé.');

            return Command::INVALID;
        }
        $user = (new User())->setEmail($email)
            ->setNom((string) $io->ask('Nom'))
            ->setPrenom((string) $io->ask('Prénom'))
            ->setRoles(['ROLE_ADMIN']);
        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            $io->error((string) $errors);

            return Command::INVALID;
        }
        $password = $io->askHidden('Mot de passe (12 caractères minimum)', static function (?string $value): string {
            if ($value === null || mb_strlen($value) < 12 || mb_strlen($value) > 4096) {
                throw new \RuntimeException('Le mot de passe doit contenir entre 12 et 4096 caractères.');
            }

            return $value;
        });
        $user->setPassword($this->hasher->hashPassword($user, $password));
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $io->success('Administrateur créé.');

        return Command::SUCCESS;
    }
}
