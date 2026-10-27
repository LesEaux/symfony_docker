<?php

namespace App\Tests\Controller;

use App\Command\CreateDefaultUserCommand;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AccountManager;
use App\Service\PasswordResetter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AccountTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    public function testThemeIsRememberedForTheCurrentUser(): void
    {
        $user = $this->user();
        $other = $this->user(email: 'other@example.test');
        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/profile');
        self::assertSelectorExists('html[data-bs-theme="dark"]');
        $form = $crawler->filter('form[action="/profile/theme"]')->first()->form();
        $this->client->submit($form, ['theme' => 'light']);
        self::assertResponseRedirects('/profile');
        $this->client->followRedirect();
        self::assertSelectorExists('html[data-bs-theme="light"]');
        self::assertSelectorExists('button[role="switch"][aria-checked="false"]');
        $this->client->request('GET', '/');
        self::assertSelectorExists('html[data-bs-theme="light"]');
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertSame('light', $em->find(User::class, $user->getId())->getTheme());
        self::assertSame('dark', $em->find(User::class, $other->getId())->getTheme());
        // A fresh login still reads the stored preference, rather than a session-only setting.
        $this->client->restart();
        $this->client->loginUser($em->find(User::class, $user->getId()));
        $crawler = $this->client->request('GET', '/profile');
        self::assertSelectorExists('html[data-bs-theme="light"]');
        $form = $crawler->filter('form[action="/profile/theme"]')->first()->form();
        $this->client->submit($form, ['theme' => 'dark'], ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseIsSuccessful();
        self::assertJsonStringEqualsJsonString('{"theme":"dark"}', $this->client->getResponse()->getContent());
    }

    public function testThemeChangeRejectsInvalidValuesAndCsrfTokens(): void
    {
        $user = $this->user();
        $this->client->loginUser($user);
        $this->client->request('POST', '/profile/theme', ['theme' => 'light', '_token' => 'invalid']);
        self::assertResponseStatusCodeSame(403);
        $crawler = $this->client->request('GET', '/profile');
        $form = $crawler->filter('form[action="/profile/theme"]')->first()->form();
        $this->client->submit($form, ['theme' => 'invalid']);
        self::assertResponseStatusCodeSame(400);
        self::assertSame('dark', static::getContainer()->get(EntityManagerInterface::class)->find(User::class, $user->getId())->getTheme());
    }

    public function testDefaultRootAccountCanLogIn(): void
    {
        $command = new CreateDefaultUserCommand($this->em, static::getContainer()->get(UserRepository::class), static::getContainer()->get(UserPasswordHasherInterface::class), 'dev');
        $tester = new CommandTester($command);
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();
        $crawler = $this->client->request('GET', '/login');
        $this->client->submit($crawler->selectButton('Se connecter')->form(), ['email' => 'root', 'password' => 'root']);
        self::assertResponseRedirects('/');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/user/new');
        self::assertResponseIsSuccessful();
    }

    public function testDefaultAccountCannotBeCreatedInProduction(): void
    {
        $command = new CreateDefaultUserCommand($this->em, static::getContainer()->get(UserRepository::class), static::getContainer()->get(UserPasswordHasherInterface::class), 'prod');
        $tester = new CommandTester($command);
        self::assertSame(Command::INVALID, $tester->execute([]));
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM user'));
    }

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertSame('pdo_sqlite', $this->em->getConnection()->getParams()['driver']);
        $schema = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        static::getContainer()->get('limiter.password_reset')->create('127.0.0.1')->reset();
    }

    public function testLastAdministratorCannotBeDeleted(): void
    {
        $admin = $this->user(['ROLE_ADMIN']);
        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/user');
        self::assertResponseIsSuccessful();
        $this->client->submit($crawler->selectButton('Supprimer')->form());
        self::assertResponseRedirects('/user');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'dernier administrateur');
        self::assertSame(1, (int) static::getContainer()->get(EntityManagerInterface::class)->getConnection()->fetchOne('SELECT COUNT(*) FROM user'));
    }

    public function testLastAdministratorCannotBeDeactivatedOrDemoted(): void
    {
        $admin = $this->user(['ROLE_ADMIN']);
        $accounts = static::getContainer()->get(AccountManager::class);
        foreach (['deactivate', 'demote'] as $action) {
            $admin->setRoles($action === 'demote' ? ['ROLE_USER'] : ['ROLE_ADMIN']);
            $admin->setIsActive($action !== 'deactivate');
            try {
                $accounts->save($admin);
                self::fail('Last administrator protection must reject this change.');
            } catch (\DomainException $error) {
                self::assertStringContainsString('dernier administrateur', $error->getMessage());
            }
            $this->em->refresh($admin);
            self::assertTrue($admin->isActiveAdmin());
        }
    }

    public function testAnotherAdministratorAllowsDeactivation(): void
    {
        $first = $this->user(['ROLE_ADMIN']);
        $this->user(['ROLE_ADMIN'], 'second@example.test');
        $first->setIsActive(false);
        static::getContainer()->get(AccountManager::class)->save($first);
        $this->em->refresh($first);
        self::assertFalse($first->isActive());
    }

    public function testInactiveAccountCannotLogIn(): void
    {
        $user = $this->user();
        $user->setIsActive(false);
        $this->em->flush();
        $crawler = $this->client->request('GET', '/login');
        $this->client->submit($crawler->selectButton('Se connecter')->form(), ['email' => $user->getEmail(), 'password' => 'initial-password-123']);
        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertSelectorExists('.alert-danger');
        $this->client->request('GET', '/profile');
        self::assertResponseRedirects('/login');
    }

    public function testDeactivationRevokesExistingSession(): void
    {
        $user = $this->user();
        $this->client->loginUser($user);
        $this->client->request('GET', '/profile');
        self::assertResponseIsSuccessful();
        static::getContainer()->get(EntityManagerInterface::class)->getConnection()
            ->executeStatement('UPDATE user SET is_active = 0 WHERE id = ?', [$user->getId()]);
        $this->client->request('GET', '/profile');
        self::assertResponseRedirects('/login');
    }

    public function testProfileRequiresCurrentPasswordAndDoesNotChangeRoles(): void
    {
        $user = $this->user();
        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/profile');
        self::assertSelectorNotExists('[name="profile_form[roles]"]');
        $this->client->submit($crawler->selectButton('Enregistrer')->form(), [
            'profile_form[nom]' => 'Nouveau nom', 'profile_form[currentPassword]' => 'incorrect',
        ]);
        self::assertResponseStatusCodeSame(422);
        $crawler = $this->client->request('GET', '/profile');
        $this->client->submit($crawler->selectButton('Enregistrer')->form(), [
            'profile_form[nom]' => 'Nouveau nom', 'profile_form[currentPassword]' => 'initial-password-123',
        ]);
        self::assertResponseRedirects('/profile');
        $stored = static::getContainer()->get(EntityManagerInterface::class)->getRepository(User::class)->findOneBy(['email' => 'user@example.test']);
        self::assertSame('Nouveau nom', $stored->getNom());
        self::assertNotContains('ROLE_ADMIN', $stored->getRoles());
    }

    public function testPasswordResetEmailAndSingleUseToken(): void
    {
        $user = $this->user();
        $crawler = $this->client->request('GET', '/forgot-password');
        $this->client->submit($crawler->selectButton('Envoyer le lien')->form(), ['form[email]' => $user->getEmail()]);
        self::assertResponseRedirects('/login');
        self::assertEmailCount(1);
        $message = self::getMailerMessage();
        self::assertNotNull($message);
        preg_match('~/reset-password/([a-f0-9]{64})~', $message->getTextBody(), $matches);
        self::assertCount(2, $matches);
        $token = $matches[1];
        $db = static::getContainer()->get(EntityManagerInterface::class)->getConnection();
        self::assertSame(hash('sha256', $token), $db->fetchOne('SELECT password_reset_token FROM user'));
        $crawler = $this->client->request('GET', '/reset-password/'.$token);
        self::assertResponseIsSuccessful();
        $this->client->submit($crawler->selectButton('Réinitialiser le mot de passe')->form(), [
            'password_form[plainPassword][first]' => 'replacement-password-123',
            'password_form[plainPassword][second]' => 'replacement-password-123',
        ]);
        self::assertResponseRedirects('/login');
        $stored = static::getContainer()->get(EntityManagerInterface::class)->getRepository(User::class)->findOneBy(['email' => 'user@example.test']);
        self::assertTrue(static::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($stored, 'replacement-password-123'));
        $this->client->request('GET', '/reset-password/'.$token);
        self::assertResponseRedirects('/forgot-password');
    }

    public function testUnknownEmailHasSamePublicResponse(): void
    {
        $crawler = $this->client->request('GET', '/forgot-password');
        $this->client->submit($crawler->selectButton('Envoyer le lien')->form(), ['form[email]' => 'unknown@example.test']);
        self::assertResponseRedirects('/login');
        self::assertEmailCount(0);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Si un compte actif');
    }

    public function testExpiredAndInactiveTokensAreRejected(): void
    {
        $user = $this->user();
        $token = bin2hex(random_bytes(32));
        $user->requestPasswordReset(hash('sha256', $token), new \DateTimeImmutable('-1 hour'));
        $this->em->flush();
        $resetter = static::getContainer()->get(PasswordResetter::class);
        self::assertNull($resetter->find($token));
        $user->requestPasswordReset(hash('sha256', $token), new \DateTimeImmutable());
        $this->em->flush();
        self::assertNotNull($resetter->find($token));
        $user->setIsActive(false);
        $this->em->flush();
        self::assertNull($resetter->find($token));
    }

    public function testPasswordChangeRequiresCurrentPassword(): void
    {
        $user = $this->user();
        $this->client->loginUser($user);
        $crawler = $this->client->request('GET', '/profile/password');
        $this->client->submit($crawler->selectButton('Modifier le mot de passe')->form(), [
            'password_form[currentPassword]' => 'initial-password-123',
            'password_form[plainPassword][first]' => 'new-personal-password-123',
            'password_form[plainPassword][second]' => 'new-personal-password-123',
        ]);
        self::assertResponseRedirects('/profile');
        $stored = static::getContainer()->get(EntityManagerInterface::class)->getRepository(User::class)->findOneBy(['email' => 'user@example.test']);
        self::assertTrue(static::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($stored, 'new-personal-password-123'));
    }

    private function user(array $roles = ['ROLE_USER'], string $email = 'user@example.test'): User
    {
        $user = (new User())->setEmail($email)->setNom('Test')->setPrenom('Compte')->setRoles($roles);
        $user->setPassword(static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, 'initial-password-123'));
        $this->em->persist($user);
        $this->em->flush();

        return $user;
    }
}
