<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:init', description: 'Initialiser la configuration locale, les tables et le premier administrateur.')]
final class InitCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $em,
        private UserRepository $users,
        private UserPasswordHasherInterface $hasher,
        #[Autowire('%kernel.project_dir%')] private string $projectDir,
        #[Autowire('%kernel.environment%')] private string $environment,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('name', null, InputOption::VALUE_REQUIRED, 'Nom de votre application')
            ->addOption('company', null, InputOption::VALUE_REQUIRED, 'Nom de votre entreprise')
            ->addOption('demo', null, InputOption::VALUE_NONE, 'Ajouter trois comptes de démonstration locaux');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if ($this->environment !== 'dev') {
            $io->error('app:init est réservé au développement. En production, utilisez les migrations et app:create-admin.');

            return Command::INVALID;
        }
        $path = $this->projectDir.'/.env.local';
        $local = is_file($path) ? (string) file_get_contents($path) : '';
        foreach (['APP_NAME' => $input->getOption('name'), 'APP_COMPANY' => $input->getOption('company'), 'APP_SECRET' => bin2hex(random_bytes(32))] as $key => $value) {
            if ($value !== null && !preg_match('/^'.preg_quote($key, '/').'=/m', $local)) {
                $encoded = str_replace('$', '\\$', json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                $local .= "\n".$key.'='.$encoded."\n";
            }
        }
        if (file_put_contents($path, $local) === false) {
            $io->error('Impossible d’écrire .env.local.');

            return Command::FAILURE;
        }
        chmod($path, 0600);
        // When initialization runs as root in Docker, only the application account
        // should own and read this file. Keep the private 0600 permissions.
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            chown($path, 'www-data');
        }
        $migration = new ArrayInput(['--no-interaction' => true]);
        $migration->setInteractive(false);
        $application = $this->getApplication();
        if ($application === null) {
            return Command::FAILURE;
        }
        if ($application->find('doctrine:migrations:migrate')->run($migration, $output) !== Command::SUCCESS) {
            return Command::FAILURE;
        }
        if ($application->find('app:create-default-user')->run(new ArrayInput([]), $output) !== Command::SUCCESS) {
            return Command::FAILURE;
        }
        if ($input->getOption('demo')) {
            $password = bin2hex(random_bytes(12));
            foreach (['alice', 'bruno', 'claire'] as $name) {
                $email = $name.'@example.test';
                if (!$this->users->findOneBy(['email' => $email])) {
                    $user = (new User())->setEmail($email)->setNom('Démo')->setPrenom(ucfirst($name))->setRoles(['ROLE_USER']);
                    $user->setPassword($this->hasher->hashPassword($user, $password));
                    $this->em->persist($user);
                    $io->writeln('Compte de démonstration : '.$email);
                }
            }
            $this->em->flush();
            $io->note('Mot de passe des nouveaux comptes de démonstration : '.$password);
        }
        $admins = $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM user WHERE is_active = 1 AND roles LIKE ?', ['%"ROLE_ADMIN"%']);
        if ((int) $admins === 0 && $input->isInteractive()) {
            $adminInput = new ArrayInput([]);
            if ($application->find('app:create-admin')->run($adminInput, $output) !== Command::SUCCESS) {
                return Command::FAILURE;
            }
        } elseif ((int) $admins === 0) {
            $io->note('Créez votre administrateur avec app:create-admin.');
        }
        $io->success('Initialisation terminée. Les réglages existants de .env.local ont été conservés.');

        return Command::SUCCESS;
    }
}
