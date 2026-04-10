<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\AuthToken;
use App\Entity\Photo;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class PhotoControllerTest extends WebTestCase
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

    public function testLikePhotoAsGuestRedirectsWithError(): void
    {
        $user = $this->createUser('owner');
        $photo = $this->createPhoto($user, 'Test photo');

        $this->client->request('GET', '/photo/' . $photo->getId() . '/like');

        $this->assertResponseRedirects('/');
    }

    public function testLikePhotoAsLoggedInUser(): void
    {
        $owner = $this->createUser('owner');
        $photo = $this->createPhoto($owner, 'Likeable photo');
        $liker = $this->createUserWithToken('liker', 'liker_token');

        // Login
        $this->client->request('GET', '/auth/liker/liker_token');
        $this->client->followRedirect();

        // Like the photo
        $this->client->request('GET', '/photo/' . $photo->getId() . '/like');
        $this->assertResponseRedirects('/');

        // Verify counter incremented
        $this->em->clear();
        $updatedPhoto = $this->em->getRepository(Photo::class)->find($photo->getId());
        $this->assertSame(1, $updatedPhoto->getLikeCounter());
    }

    public function testUnlikePhotoToggle(): void
    {
        $owner = $this->createUser('owner');
        $photo = $this->createPhoto($owner, 'Toggle photo');
        $liker = $this->createUserWithToken('liker', 'liker_token');

        // Login
        $this->client->request('GET', '/auth/liker/liker_token');
        $this->client->followRedirect();

        // Like
        $this->client->request('GET', '/photo/' . $photo->getId() . '/like');
        $this->client->followRedirect();

        // Unlike
        $this->client->request('GET', '/photo/' . $photo->getId() . '/like');
        $this->assertResponseRedirects('/');

        // Verify counter back to 0
        $this->em->clear();
        $updatedPhoto = $this->em->getRepository(Photo::class)->find($photo->getId());
        $this->assertSame(0, $updatedPhoto->getLikeCounter());
    }

    public function testLikeNonExistentPhotoReturns404(): void
    {
        $liker = $this->createUserWithToken('liker', 'liker_token');

        // Login
        $this->client->request('GET', '/auth/liker/liker_token');
        $this->client->followRedirect();

        $this->client->request('GET', '/photo/99999/like');
        $this->assertResponseStatusCodeSame(404);
    }

    /**
     * Bug #8: Like action uses GET method - no CSRF protection.
     * This test documents that likes work via GET, which is a security issue.
     */
    public function testLikeWorksViaGetRequest(): void
    {
        $owner = $this->createUser('owner');
        $photo = $this->createPhoto($owner, 'GET like photo');
        $liker = $this->createUserWithToken('liker', 'liker_token');

        $this->client->request('GET', '/auth/liker/liker_token');
        $this->client->followRedirect();

        // GET request to like - should require POST but works with GET (bug #8)
        $this->client->request('GET', '/photo/' . $photo->getId() . '/like');
        $this->assertResponseRedirects('/');
    }

    private function createUser(string $username): User
    {
        $user = new User();
        $user->setUsername($username);
        $user->setEmail($username . '@example.com');
        $this->em->persist($user);
        $this->em->flush();
        return $user;
    }

    private function createUserWithToken(string $username, string $tokenValue): User
    {
        $user = $this->createUser($username);
        $token = new AuthToken();
        $token->setToken($tokenValue);
        $token->setUser($user);
        $this->em->persist($token);
        $this->em->flush();
        return $user;
    }

    private function createPhoto(User $user, string $description): Photo
    {
        $photo = new Photo();
        $photo->setDescription($description);
        $photo->setImageUrl('https://example.com/photo.jpg');
        $photo->setUser($user);
        $this->em->persist($photo);
        $this->em->flush();
        return $photo;
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
