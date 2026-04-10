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

        $this->client->request('POST', '/photo/' . $photo->getId() . '/like', [
            '_token' => 'dummy',
        ]);

        $this->assertResponseRedirects('/');
    }

    public function testLikePhotoAsLoggedInUser(): void
    {
        $owner = $this->createUser('owner');
        $photo = $this->createPhoto($owner, 'Likeable photo');
        $liker = $this->createUserWithToken('liker', 'liker_token');

        $this->login('liker', 'liker_token');

        $csrfToken = $this->getCsrfTokenFromHomePage($photo->getId());
        $this->client->request('POST', '/photo/' . $photo->getId() . '/like', [
            '_token' => $csrfToken,
        ]);
        $this->assertResponseRedirects('/');

        $this->em->clear();
        $updatedPhoto = $this->em->getRepository(Photo::class)->find($photo->getId());
        $this->assertSame(1, $updatedPhoto->getLikeCounter());
    }

    public function testUnlikePhotoToggle(): void
    {
        $owner = $this->createUser('owner');
        $photo = $this->createPhoto($owner, 'Toggle photo');
        $liker = $this->createUserWithToken('liker', 'liker_token');

        $this->login('liker', 'liker_token');

        // Like
        $csrfToken = $this->getCsrfTokenFromHomePage($photo->getId());
        $this->client->request('POST', '/photo/' . $photo->getId() . '/like', [
            '_token' => $csrfToken,
        ]);
        $this->client->followRedirect();

        // Unlike
        $csrfToken = $this->getCsrfTokenFromHomePage($photo->getId());
        $this->client->request('POST', '/photo/' . $photo->getId() . '/like', [
            '_token' => $csrfToken,
        ]);
        $this->assertResponseRedirects('/');

        $this->em->clear();
        $updatedPhoto = $this->em->getRepository(Photo::class)->find($photo->getId());
        $this->assertSame(0, $updatedPhoto->getLikeCounter());
    }

    public function testLikeNonExistentPhotoReturnsNotFound(): void
    {
        $owner = $this->createUser('owner');
        $photo = $this->createPhoto($owner, 'Real photo');
        $liker = $this->createUserWithToken('liker', 'liker_token');

        $this->login('liker', 'liker_token');

        // We need a real CSRF token - get it from home page for the real photo,
        // then delete the photo to simulate "not found" scenario
        $csrfToken = $this->getCsrfTokenFromHomePage($photo->getId());
        $photoId = $photo->getId();

        $this->em->getConnection()->executeStatement('DELETE FROM photos WHERE id = ?', [$photoId]);

        $this->client->request('POST', '/photo/' . $photoId . '/like', [
            '_token' => $csrfToken,
        ]);
        $this->assertResponseStatusCodeSame(404);
    }

    public function testLikeViaGetMethodIsNotAllowed(): void
    {
        $owner = $this->createUser('owner');
        $photo = $this->createPhoto($owner, 'GET like photo');
        $liker = $this->createUserWithToken('liker', 'liker_token');

        $this->login('liker', 'liker_token');

        $this->client->request('GET', '/photo/' . $photo->getId() . '/like');
        $this->assertResponseStatusCodeSame(405);
    }

    public function testLikeWithInvalidCsrfTokenIsRejected(): void
    {
        $owner = $this->createUser('owner');
        $photo = $this->createPhoto($owner, 'CSRF photo');
        $liker = $this->createUserWithToken('liker', 'liker_token');

        $this->login('liker', 'liker_token');

        $this->client->request('POST', '/photo/' . $photo->getId() . '/like', [
            '_token' => 'invalid_csrf_token',
        ]);
        $this->assertResponseRedirects('/');

        $this->em->clear();
        $updatedPhoto = $this->em->getRepository(Photo::class)->find($photo->getId());
        $this->assertSame(0, $updatedPhoto->getLikeCounter());
    }

    private function login(string $username, string $token): void
    {
        $this->client->request('POST', '/auth/login', [
            'username' => $username,
            'token' => $token,
        ]);
        $this->client->followRedirect();
    }

    private function getCsrfTokenFromHomePage(int $photoId): string
    {
        $crawler = $this->client->request('GET', '/');
        $form = $crawler->filter('form[action$="/photo/' . $photoId . '/like"]');

        return $form->filter('input[name="_token"]')->attr('value');
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
