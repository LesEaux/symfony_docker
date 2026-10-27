<?php

namespace App\Tests\Command;

use App\Command\CreateDefaultUserCommand;
use App\Command\InitCommand;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class InitCommandTest extends KernelTestCase
{
    public function testInitializationPreservesConfigurationAndDemoAccounts(): void
    {
        self::bootKernel();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertSame('pdo_sqlite', $em->getConnection()->getParams()['driver']);
        $schema = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        $path = sys_get_temp_dir().'/template-init-'.bin2hex(random_bytes(8));
        mkdir($path);
        try {
            $command = new InitCommand($em, static::getContainer()->get(UserRepository::class), static::getContainer()->get(UserPasswordHasherInterface::class), $path, 'dev');
            $application = new Application();
            $migration = (new Command('doctrine:migrations:migrate'))->setCode(static fn (): int => Command::SUCCESS);
            $application->addCommand($migration);
            $application->addCommand($command);
            $application->addCommand(new CreateDefaultUserCommand($em, static::getContainer()->get(UserRepository::class), static::getContainer()->get(UserPasswordHasherInterface::class), 'dev'));
            $tester = new CommandTester($command);
            $tester->execute(['--name' => 'Mon $outil', '--company' => 'Entreprise', '--demo' => true], ['interactive' => false]);
            $tester->assertCommandIsSuccessful();
            $contents = file_get_contents($path.'/.env.local');
            $values = (new Dotenv())->parse($contents);
            self::assertSame('Mon $outil', $values['APP_NAME']);
            self::assertSame(64, strlen($values['APP_SECRET']));
            self::assertSame(4, (int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM user'));
            $root = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'root@example.test']);
            self::assertTrue($root->isActiveAdmin());
            self::assertTrue(static::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($root, 'root'));
            $tester->execute(['--name' => 'Autre nom', '--demo' => true], ['interactive' => false]);
            $tester->assertCommandIsSuccessful();
            self::assertSame($contents, file_get_contents($path.'/.env.local'));
            self::assertSame(4, (int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM user'));
        } finally {
            @unlink($path.'/.env.local');
            rmdir($path);
        }
    }
}
