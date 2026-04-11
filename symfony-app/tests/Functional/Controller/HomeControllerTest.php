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

    public function testFilterByLocation(): void
    {
        $user = $this->createUser('photog');
        $this->createPhoto($user, 'Mountain view', location: 'Rocky Mountains');
        $this->createPhoto($user, 'City view', location: 'New York');

        $this->client->request('GET', '/', ['location' => 'Rocky']);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Mountain view');
        $this->assertSelectorTextNotContains('body', 'City view');
    }

    public function testFilterByCamera(): void
    {
        $user = $this->createUser('photog');
        $this->createPhoto($user, 'Canon shot', camera: 'Canon EOS R5');
        $this->createPhoto($user, 'Nikon shot', camera: 'Nikon Z9');

        $this->client->request('GET', '/', ['camera' => 'Canon']);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Canon shot');
        $this->assertSelectorTextNotContains('body', 'Nikon shot');
    }

    public function testFilterByDescription(): void
    {
        $user = $this->createUser('photog');
        $this->createPhoto($user, 'Beautiful sunset on the beach');
        $this->createPhoto($user, 'Snowy mountain peak');

        $this->client->request('GET', '/', ['description' => 'sunset']);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Beautiful sunset on the beach');
        $this->assertSelectorTextNotContains('body', 'Snowy mountain peak');
    }

    public function testFilterByUsername(): void
    {
        $user1 = $this->createUser('alice');
        $user2 = $this->createUser('bob');
        $this->createPhoto($user1, 'Alice photo');
        $this->createPhoto($user2, 'Bob photo');

        $this->client->request('GET', '/', ['username' => 'alice']);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Alice photo');
        $this->assertSelectorTextNotContains('body', 'Bob photo');
    }

    public function testFilterByDateRange(): void
    {
        $user = $this->createUser('photog');
        $this->createPhoto($user, 'Old photo', takenAt: new \DateTimeImmutable('2024-01-15'));
        $this->createPhoto($user, 'Recent photo', takenAt: new \DateTimeImmutable('2025-06-20'));

        $this->client->request('GET', '/', ['date_from' => '2025-01-01', 'date_to' => '2025-12-31']);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Recent photo');
        $this->assertSelectorTextNotContains('body', 'Old photo');
    }

    public function testFilterCombination(): void
    {
        $user1 = $this->createUser('alice');
        $user2 = $this->createUser('bob');
        $this->createPhoto($user1, 'Alice mountain', location: 'Rocky Mountains');
        $this->createPhoto($user1, 'Alice city', location: 'New York');
        $this->createPhoto($user2, 'Bob mountain', location: 'Rocky Mountains');

        $this->client->request('GET', '/', ['location' => 'Rocky', 'username' => 'alice']);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Alice mountain');
        $this->assertSelectorTextNotContains('body', 'Alice city');
        $this->assertSelectorTextNotContains('body', 'Bob mountain');
    }

    public function testNoFiltersReturnsAll(): void
    {
        $user = $this->createUser('photog');
        $this->createPhoto($user, 'Photo one');
        $this->createPhoto($user, 'Photo two');

        $this->client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Photo one');
        $this->assertSelectorTextContains('body', 'Photo two');
    }

    public function testFilterNoResults(): void
    {
        $user = $this->createUser('photog');
        $this->createPhoto($user, 'Some photo', location: 'Paris');

        $this->client->request('GET', '/', ['location' => 'Nonexistent']);

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'No photos yet');
    }

    public function testFilterFormPreservesValues(): void
    {
        $user = $this->createUser('photog');
        $this->createPhoto($user, 'Test photo');

        $crawler = $this->client->request('GET', '/', ['location' => 'Paris', 'camera' => 'Canon']);

        $this->assertResponseIsSuccessful();
        $locationInput = $crawler->filter('#filter-location');
        $cameraInput = $crawler->filter('#filter-camera');
        $this->assertSame('Paris', $locationInput->attr('value'));
        $this->assertSame('Canon', $cameraInput->attr('value'));
    }

    private function createPhoto(
        User $user,
        string $description,
        ?string $location = null,
        ?string $camera = null,
        ?\DateTimeImmutable $takenAt = null,
    ): Photo {
        $photo = new Photo();
        $photo->setDescription($description);
        $photo->setImageUrl('https://example.com/' . uniqid() . '.jpg');
        $photo->setUser($user);
        if ($location !== null) {
            $photo->setLocation($location);
        }
        if ($camera !== null) {
            $photo->setCamera($camera);
        }
        if ($takenAt !== null) {
            $photo->setTakenAt($takenAt);
        }
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
