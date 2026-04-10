<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\Photo;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class HomeControllerTest extends WebTestCase
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

    public function testHomePageReturnsOk(): void
    {
        $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();
    }

    public function testHomePageDisplaysPhotos(): void
    {
        $user = $this->createUser('photographer');
        $this->createPhoto($user, 'Beautiful sunset over the mountains');

        $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Beautiful sunset over the mountains');
    }

    public function testHomePageAsLoggedInUser(): void
    {
        $user = $this->createUser('viewer');
        $this->createPhoto($user, 'Test photo');

        $session = $this->getSession();
        $session->set('user_id', $user->getId());
        $session->save();

        $this->client->request('GET', '/');
        $this->assertResponseIsSuccessful();
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

    private function getSession(): \Symfony\Component\HttpFoundation\Session\SessionInterface
    {
        $container = static::getContainer();
        return $container->get('session.factory')->createSession();
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
