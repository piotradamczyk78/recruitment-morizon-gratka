<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\AuthToken;
use App\Entity\User;
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
