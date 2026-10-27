<?php

namespace App\Tests\Controller;

use App\Entity\CompanySettings;
use App\Entity\User;
use App\Service\CompanyLogoStorage;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class CompanyTest extends WebTestCase
{
    private KernelBrowser $client;
    private array $files = [];

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        self::assertSame('pdo_sqlite', $em->getConnection()->getParams()['driver']);
        $schema = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->files);
        parent::tearDown();
    }

    public function testAdminCanUploadReplaceAndRemoveTheCompanyLogo(): void
    {
        $this->login(['ROLE_ADMIN']);
        $first = $this->upload();
        $url = '/company/logo/'.$first;
        $this->client->request('GET', '/');
        self::assertSelectorExists('.brand img.company-logo[src="'.$url.'"]');
        self::assertSelectorExists('.topbar img.company-logo[src="'.$url.'"]');
        self::assertSelectorExists('.sidebar-footer img.company-logo[src="'.$url.'"]');
        $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/png');
        self::assertResponseHeaderSame('X-Content-Type-Options', 'nosniff');
        $second = $this->upload();
        self::assertNotSame($first, $second);
        self::assertFileDoesNotExist(static::getContainer()->get(CompanyLogoStorage::class)->getPath($first));
        $crawler = $this->client->request('GET', '/admin/company');
        $this->client->submit($crawler->selectButton('Enregistrer le logo')->form(), ['company_logo_form[removeLogo]' => true]);
        self::assertResponseRedirects('/admin/company');
        self::assertNull(static::getContainer()->get(EntityManagerInterface::class)->find(CompanySettings::class, 1)->getLogoFilename());
        $this->client->request('GET', '/');
        self::assertSelectorExists('.brand svg.brand-mark');
        self::assertSelectorNotExists('.brand img.company-logo');
        $this->client->request('GET', '/company/logo/'.$second);
        self::assertResponseStatusCodeSame(404);
    }

    public function testLogoIsAvailableOnThePublicLoginPage(): void
    {
        $this->login(['ROLE_ADMIN']);
        $filename = $this->upload();
        $this->client->restart();
        $this->client->request('GET', '/login');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.brand img.company-logo');
        $this->client->request('GET', '/company/logo/'.$filename);
        self::assertResponseIsSuccessful();
    }

    public function testRegularUsersCannotChangeTheCompanyLogo(): void
    {
        $this->login(['ROLE_USER']);
        $this->client->request('GET', '/admin/company');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/admin/company', ['company_logo_form' => ['removeLogo' => true]]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testInvalidFilesAndCsrfTokensAreRejected(): void
    {
        $this->login(['ROLE_ADMIN']);
        $crawler = $this->client->request('GET', '/admin/company');
        $form = $crawler->selectButton('Enregistrer le logo')->form();
        $this->client->request('POST', '/admin/company', $form->getPhpValues(), [
            'company_logo_form' => ['logo' => $this->file('<svg xmlns="http://www.w3.org/2000/svg"></svg>', 'logo.svg', 'image/svg+xml')],
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('.invalid-feedback');
        self::assertNull(static::getContainer()->get(EntityManagerInterface::class)->find(CompanySettings::class, 1));
        $this->client->request('POST', '/admin/company', ['company_logo_form' => ['removeLogo' => true, '_token' => 'invalid']]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorExists('.alert-danger');
    }

    private function upload(): string
    {
        $crawler = $this->client->request('GET', '/admin/company');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Enregistrer le logo')->form();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jOmQAAAAASUVORK5CYII=', true);
        $this->client->request('POST', '/admin/company', $form->getPhpValues(), [
            'company_logo_form' => ['logo' => $this->file($png, 'logo.png', 'image/png')],
        ]);
        $filename = static::getContainer()->get(EntityManagerInterface::class)->find(CompanySettings::class, 1)?->getLogoFilename();
        if ($filename !== null) {
            $this->files[] = static::getContainer()->get(CompanyLogoStorage::class)->getPath($filename);
        }
        self::assertResponseRedirects('/admin/company');
        self::assertNotNull($filename);
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}\.png$/', $filename);

        return $filename;
    }

    private function file(string $contents, string $name, string $mime): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'company-logo-test-');
        file_put_contents($path, $contents);
        $this->files[] = $path;

        return new UploadedFile($path, $name, $mime, null, true);
    }

    private function login(array $roles): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $user = (new User())->setEmail('company-admin@example.test')->setNom('Test')->setPrenom('Compte')->setRoles($roles)->setPassword('unused-hash');
        $em->persist($user);
        $em->flush();
        $this->client->loginUser($user);
    }
}
