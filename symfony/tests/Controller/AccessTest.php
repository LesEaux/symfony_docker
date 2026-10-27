<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AccessTest extends WebTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertSame('pdo_sqlite', $em->getConnection()->getParams()['driver']);
        $schema = new SchemaTool($em);
        $schema->updateSchema($em->getMetadataFactory()->getAllMetadata());
        self::ensureKernelShutdown();
    }

    public function testPublicPagesRender(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');
        self::assertResponseIsSuccessful();
        $client->request('GET', '/login');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="_csrf_token"]');
    }

    public function testAnonymousUsersMustLogIn(): void
    {
        $client = static::createClient();
        $client->request('GET', '/user');
        self::assertResponseRedirects('/login');
    }

    public function testRegularUserCannotDeleteOrEditUsers(): void
    {
        $client = static::createClient();
        $user = $this->createUser(['ROLE_USER']);
        $id = $user->getId();
        $client->loginUser($user);
        $client->request('POST', '/user/'.$id);
        self::assertResponseStatusCodeSame(403);
        $client->request('GET', '/user/'.$id.'/edit');
        self::assertResponseStatusCodeSame(403);
        $client->request('GET', '/user/new');
        self::assertResponseStatusCodeSame(403);
    }

    public function testAdminCanChangeOrKeepPassword(): void
    {
        $client = static::createClient();
        $user = $this->createUser(['ROLE_ADMIN']);
        $id = $user->getId();
        $client->loginUser($user);
        $crawler = $client->request('GET', '/user/'.$id.'/edit');
        self::assertResponseIsSuccessful();
        $client->submit($crawler->selectButton('Enregistrer')->form(), [
            'user_form[plainPassword]' => 'new-password-12345',
        ]);
        self::assertResponseRedirects('/user');
        $stored = static::getContainer()->get(EntityManagerInterface::class)->find(User::class, $id);
        self::assertTrue(static::getContainer()->get(UserPasswordHasherInterface::class)->isPasswordValid($stored, 'new-password-12345'));
        $hash = $stored->getPassword();
        $crawler = $client->request('GET', '/user/'.$id.'/edit');
        $client->submit($crawler->selectButton('Enregistrer')->form(), ['user_form[plainPassword]' => '']);
        self::assertResponseRedirects('/user');
        $stored = static::getContainer()->get(EntityManagerInterface::class)->find(User::class, $id);
        self::assertSame($hash, $stored->getPassword());
        $client->request('GET', '/user/'.$id);
        self::assertResponseIsSuccessful();
    }

    private function createUser(array $roles): User
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertSame('pdo_sqlite', $em->getConnection()->getParams()['driver']);
        $schema = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        $user = (new User())->setEmail('test@example.com')->setNom('Test')->setPrenom('Compte')->setRoles($roles)->setPassword('unused-hash');
        $em->persist($user);
        $em->flush();

        return $user;
    }
}
