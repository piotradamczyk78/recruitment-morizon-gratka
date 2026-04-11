<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\AuthToken;
use App\Entity\User;
use App\Service\InvalidPhoenixTokenException;
use App\Service\PhotoImportService;
use App\Service\RateLimitExceededException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ProfileControllerTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->cleanDatabase();
    }

    protected function tearDown(): void
    {
        $this->cleanDatabase();
        parent::tearDown();
    }

    public function testProfileAsGuestRedirects(): void
    {
        $this->client->request('GET', '/profile');
        $this->assertResponseRedirects('/');
    }

    public function testProfileAsLoggedInUser(): void
    {
        $user = $this->createUserWithToken('profileuser', 'profile_token');

        // Login
        $this->client->request('POST', '/auth/login', ['username' => 'profileuser', 'token' => 'profile_token']);
        $this->client->followRedirect();

        $this->client->request('GET', '/profile');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'profileuser');
    }

    public function testProfileWithDeletedUserClearsSessionAndRedirects(): void
    {
        $user = $this->createUserWithToken('tempuser', 'temp_token');

        // Login
        $this->client->request('POST', '/auth/login', ['username' => 'tempuser', 'token' => 'temp_token']);
        $this->client->followRedirect();

        // Delete user from database while session is active
        $userId = $user->getId();
        $connection = $this->em->getConnection();
        $connection->executeStatement('DELETE FROM auth_tokens WHERE user_id = ' . $userId);
        $connection->executeStatement('DELETE FROM users WHERE id = ' . $userId);

        // Access profile - should clear session and redirect
        $this->client->request('GET', '/profile');
        $this->assertResponseRedirects('/');
    }

    public function testSavePhoenixTokenAsGuestRedirects(): void
    {
        $this->client->request('POST', '/profile/phoenix-token', [
            'phoenix_api_token' => 'some-token',
            '_token' => 'fake',
        ]);
        $this->assertResponseRedirects('/');
    }

    public function testSavePhoenixTokenWithInvalidCsrf(): void
    {
        $user = $this->createUserWithToken('csrfuser', 'csrf_token_val');
        $this->client->request('POST', '/auth/login', ['username' => 'csrfuser', 'token' => 'csrf_token_val']);
        $this->client->followRedirect();

        $this->client->request('POST', '/profile/phoenix-token', [
            'phoenix_api_token' => 'some-token',
            '_token' => 'invalid-csrf',
        ]);
        $this->assertResponseRedirects('/profile');
        $this->client->followRedirect();
        $this->assertSelectorTextContains('body', 'Invalid CSRF token');
    }

    public function testSavePhoenixTokenSuccessfully(): void
    {
        $user = $this->createUserWithToken('tokenuser', 'token_val');
        $this->client->request('POST', '/auth/login', ['username' => 'tokenuser', 'token' => 'token_val']);
        $this->client->followRedirect();

        $crawler = $this->client->request('GET', '/profile');
        $csrfToken = $crawler->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', '/profile/phoenix-token', [
            'phoenix_api_token' => 'my-phoenix-api-token-123',
            '_token' => $csrfToken,
        ]);
        $this->assertResponseRedirects('/profile');
        $this->client->followRedirect();
        $this->assertSelectorTextContains('body', 'Phoenix API token saved');

        $this->em->clear();
        $updatedUser = $this->em->getRepository(User::class)->find($user->getId());
        $this->assertSame('my-phoenix-api-token-123', $updatedUser->getPhoenixApiToken());
    }

    public function testSaveEmptyPhoenixTokenClearsValue(): void
    {
        $user = $this->createUserWithToken('clearuser', 'clear_token');
        $user->setPhoenixApiToken('existing-token');
        $this->em->flush();

        $this->client->request('POST', '/auth/login', ['username' => 'clearuser', 'token' => 'clear_token']);
        $this->client->followRedirect();

        $crawler = $this->client->request('GET', '/profile');
        $csrfToken = $crawler->filter('input[name="_token"]')->attr('value');

        $this->client->request('POST', '/profile/phoenix-token', [
            'phoenix_api_token' => '',
            '_token' => $csrfToken,
        ]);
        $this->assertResponseRedirects('/profile');

        $this->em->clear();
        $updatedUser = $this->em->getRepository(User::class)->find($user->getId());
        $this->assertNull($updatedUser->getPhoenixApiToken());
    }

    public function testSavePhoenixTokenViaGetReturns405(): void
    {
        $this->client->request('GET', '/profile/phoenix-token');
        $this->assertResponseStatusCodeSame(405);
    }

    public function testImportPhotosAsGuestRedirects(): void
    {
        $this->client->request('POST', '/profile/import-photos', ['_token' => 'fake']);
        $this->assertResponseRedirects('/');
    }

    public function testImportPhotosWithInvalidCsrf(): void
    {
        $user = $this->createUserWithToken('importcsrf', 'import_csrf_tok');
        $user->setPhoenixApiToken('some-phoenix-token');
        $this->em->flush();

        $this->client->request('POST', '/auth/login', ['username' => 'importcsrf', 'token' => 'import_csrf_tok']);
        $this->client->followRedirect();

        $this->client->request('POST', '/profile/import-photos', ['_token' => 'invalid']);
        $this->assertResponseRedirects('/profile');
        $this->client->followRedirect();
        $this->assertSelectorTextContains('body', 'Invalid CSRF token');
    }

    public function testImportPhotosWithoutTokenShowsError(): void
    {
        $user = $this->createUserWithToken('notokenuser', 'notoken_val');
        // User has phoenix token set temporarily to get CSRF from import form
        $user->setPhoenixApiToken('temp');
        $this->em->flush();

        $this->client->disableReboot();
        $this->client->request('POST', '/auth/login', ['username' => 'notokenuser', 'token' => 'notoken_val']);
        $this->client->followRedirect();

        $crawler = $this->client->request('GET', '/profile');
        $csrfToken = $this->getImportCsrfToken($crawler);

        // Now remove the phoenix token to simulate "no token" state
        $this->em->clear();
        $user = $this->em->getRepository(User::class)->findOneBy(['username' => 'notokenuser']);
        $user->setPhoenixApiToken(null);
        $this->em->flush();

        $this->client->request('POST', '/profile/import-photos', ['_token' => $csrfToken]);
        $this->assertResponseRedirects('/profile');
        $this->client->followRedirect();
        $this->assertSelectorTextContains('body', 'Set your Phoenix API token first');
    }

    public function testImportPhotosSuccess(): void
    {
        $user = $this->createUserWithToken('importuser', 'import_tok');
        $user->setPhoenixApiToken('valid-phoenix-token');
        $this->em->flush();

        $this->client->disableReboot();
        $this->client->request('POST', '/auth/login', ['username' => 'importuser', 'token' => 'import_tok']);
        $this->client->followRedirect();

        $crawler = $this->client->request('GET', '/profile');
        $csrfToken = $this->getImportCsrfToken($crawler);

        $mockImportService = $this->createMock(PhotoImportService::class);
        $mockImportService->method('importPhotos')
            ->willReturn(['imported' => 3, 'skipped' => 1]);
        static::getContainer()->set(PhotoImportService::class, $mockImportService);

        $this->client->request('POST', '/profile/import-photos', ['_token' => $csrfToken]);
        $this->assertResponseRedirects('/profile');
        $this->client->followRedirect();
        $this->assertSelectorTextContains('body', 'Imported 3 photos (1 skipped)');
    }

    public function testImportPhotosInvalidToken(): void
    {
        $user = $this->createUserWithToken('badtokuser', 'badtok_val');
        $user->setPhoenixApiToken('bad-phoenix-token');
        $this->em->flush();

        $this->client->disableReboot();
        $this->client->request('POST', '/auth/login', ['username' => 'badtokuser', 'token' => 'badtok_val']);
        $this->client->followRedirect();

        $crawler = $this->client->request('GET', '/profile');
        $csrfToken = $this->getImportCsrfToken($crawler);

        $mockImportService = $this->createMock(PhotoImportService::class);
        $mockImportService->method('importPhotos')
            ->willThrowException(new InvalidPhoenixTokenException());
        static::getContainer()->set(PhotoImportService::class, $mockImportService);

        $this->client->request('POST', '/profile/import-photos', ['_token' => $csrfToken]);
        $this->assertResponseRedirects('/profile');
        $this->client->followRedirect();
        $this->assertSelectorTextContains('body', 'Invalid Phoenix API token');
    }

    public function testImportPhotosConnectionError(): void
    {
        $user = $this->createUserWithToken('connerruser', 'connerr_val');
        $user->setPhoenixApiToken('some-token');
        $this->em->flush();

        $this->client->disableReboot();
        $this->client->request('POST', '/auth/login', ['username' => 'connerruser', 'token' => 'connerr_val']);
        $this->client->followRedirect();

        $crawler = $this->client->request('GET', '/profile');
        $csrfToken = $this->getImportCsrfToken($crawler);

        $mockImportService = $this->createMock(PhotoImportService::class);
        $mockImportService->method('importPhotos')
            ->willThrowException(new \RuntimeException('Connection refused'));
        static::getContainer()->set(PhotoImportService::class, $mockImportService);

        $this->client->request('POST', '/profile/import-photos', ['_token' => $csrfToken]);
        $this->assertResponseRedirects('/profile');
        $this->client->followRedirect();
        $this->assertSelectorTextContains('body', 'Failed to connect to Phoenix API');
    }

    public function testImportPhotosRateLimited(): void
    {
        $user = $this->createUserWithToken('ratelimituser', 'ratelimit_val');
        $user->setPhoenixApiToken('some-token');
        $this->em->flush();

        $this->client->disableReboot();
        $this->client->request('POST', '/auth/login', ['username' => 'ratelimituser', 'token' => 'ratelimit_val']);
        $this->client->followRedirect();

        $crawler = $this->client->request('GET', '/profile');
        $csrfToken = $this->getImportCsrfToken($crawler);

        $mockImportService = $this->createMock(PhotoImportService::class);
        $mockImportService->method('importPhotos')
            ->willThrowException(new RateLimitExceededException(45));
        static::getContainer()->set(PhotoImportService::class, $mockImportService);

        $this->client->request('POST', '/profile/import-photos', ['_token' => $csrfToken]);
        $this->assertResponseRedirects('/profile');
        $this->client->followRedirect();
        $this->assertSelectorTextContains('body', 'Too many imports, please try again in 45 seconds');
    }

    public function testImportPhotosViaGetReturns405(): void
    {
        $this->client->request('GET', '/profile/import-photos');
        $this->assertResponseStatusCodeSame(405);
    }

    public function testImportButtonVisibleWhenTokenSet(): void
    {
        $user = $this->createUserWithToken('btnuser', 'btn_tok');
        $user->setPhoenixApiToken('my-token');
        $this->em->flush();

        $this->client->request('POST', '/auth/login', ['username' => 'btnuser', 'token' => 'btn_tok']);
        $this->client->followRedirect();

        $crawler = $this->client->request('GET', '/profile');
        $this->assertSelectorTextContains('body', 'Import Photos');
    }

    public function testImportButtonHiddenWhenNoToken(): void
    {
        $user = $this->createUserWithToken('nobtnuser', 'nobtn_tok');
        $this->client->request('POST', '/auth/login', ['username' => 'nobtnuser', 'token' => 'nobtn_tok']);
        $this->client->followRedirect();

        $crawler = $this->client->request('GET', '/profile');
        $this->assertSelectorTextNotContains('body', 'Import Photos');
    }

    private function getCsrfTokenForForm($crawler, string $formAction): string
    {
        return $crawler->filter('form[action*="' . $formAction . '"] input[name="_token"]')->attr('value');
    }

    private function getImportCsrfToken($crawler): string
    {
        return $this->getCsrfTokenForForm($crawler, 'import-photos');
    }

    private function createUserWithToken(string $username, string $tokenValue): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($username . '@example.com');
        $this->em->persist($user);
        $this->em->flush();

        $token = new AuthToken();
        $token->setToken($tokenValue);
        $token->setUser($user);
        $this->em->persist($token);
        $this->em->flush();

        return $user;
    }

    private function cleanDatabase(): void
    {
        $connection = $this->em->getConnection();
        $connection->executeStatement('DELETE FROM likes');
        $connection->executeStatement('DELETE FROM photos');
        $connection->executeStatement('DELETE FROM auth_tokens');
        $connection->executeStatement('DELETE FROM users');
    }
}
