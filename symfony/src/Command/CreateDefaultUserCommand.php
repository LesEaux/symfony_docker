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
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:create-default-user', description: 'Créer le compte administrateur root/root de développement.')]
final class CreateDefaultUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $users,
        private UserPasswordHasherInterface $hasher,
        #[Autowire('%kernel.environment%')] private string $environment,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if ($this->environment !== 'dev') {
            $io->error('Le compte par défaut est réservé au développement local.');

            return Command::INVALID;
        }
        if ($this->users->findOneBy(['email' => 'root@example.test'])) {
            $io->note('Le compte root existe déjà ; son mot de passe et ses droits sont conservés.');

            return Command::SUCCESS;
        }
        $user = (new User())->setEmail('root@example.test')->setNom('Administrateur')
            ->setPrenom('Root')->setRoles(['ROLE_ADMIN']);
        $user->setPassword($this->hasher->hashPassword($user, 'root'));
        $this->em->persist($user);
        $this->em->flush();
        $io->success('Compte administrateur local créé : identifiant root, mot de passe root.');

        return Command::SUCCESS;
    }
}
