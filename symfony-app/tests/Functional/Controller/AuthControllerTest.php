<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\AuthToken;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AuthControllerTest extends WebTestCase
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

    public function testLoginWithValidTokenRedirects(): void
    {
        $user = $this->createUserWithToken('testuser', 'valid_token_123');

        $this->login('testuser', 'valid_token_123');

        $this->assertResponseRedirects('/');
    }

    public function testLoginSetsSessionData(): void
    {
        $user = $this->createUserWithToken('testuser', 'valid_token_123');

        $this->login('testuser', 'valid_token_123');
        $this->client->followRedirect();

        $this->assertResponseIsSuccessful();
    }

    public function testLoginWithInvalidTokenReturns401(): void
    {
        $this->login('testuser', 'invalid_token');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testLoginWithNonExistentUserReturns404(): void
    {
        $user = $this->createUserWithToken('realuser', 'valid_token_123');

        $this->login('nonexistent', 'valid_token_123');

        $this->assertResponseStatusCodeSame(404);
    }

    /**
     * Bug #5: Token is not verified against the user - any valid token
     * works for any username, as long as both exist in the database.
     */
    public function testLoginDoesNotVerifyTokenBelongsToUser(): void
    {
        $user1 = $this->createUserWithToken('user1', 'token_for_user1');
        $user2 = $this->createUserWithToken('user2', 'token_for_user2');

        // Using user2's token with user1's username - should fail but doesn't
        $this->login('user1', 'token_for_user2');

        // Bug: this succeeds because token and user are checked independently
        $this->assertResponseRedirects('/');
    }

    public function testLogoutClearsSessionAndRedirects(): void
    {
        $user = $this->createUserWithToken('testuser', 'valid_token_123');

        // Login first
        $this->login('testuser', 'valid_token_123');
        $this->client->followRedirect();

        // Then logout
        $this->client->request('GET', '/logout');
        $this->assertResponseRedirects('/');
    }

    /**
     * Credentials are no longer exposed in URL - sent via POST body.
     */
    public function testLoginViaGetMethodIsNotAllowed(): void
    {
        $user = $this->createUserWithToken('testuser', 'valid_token_123');

        $this->client->request('GET', '/auth/login');

        $this->assertResponseStatusCodeSame(405);
    }

    private function login(string $username, string $token): void
    {
        $this->client->request('POST', '/auth/login', [
            'username' => $username,
            'token' => $token,
        ]);
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
