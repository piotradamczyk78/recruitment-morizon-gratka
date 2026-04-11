<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\Photo;
use App\Entity\User;
use App\Repository\PhotoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class PhotoRepositoryFilterTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private PhotoRepository $photoRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->photoRepository = static::getContainer()->get(PhotoRepository::class);
        $this->cleanDatabase();
        $this->seedData();
    }

    protected function tearDown(): void
    {
        $this->cleanDatabase();
        parent::tearDown();
    }

    private User $alice;
    private User $bob;

    private function seedData(): void
    {
        $this->alice = $this->createUser('alice');
        $this->bob = $this->createUser('bob');

        $this->createPhoto($this->alice, 'Mountain sunset', 'Rocky Mountains', 'Canon EOS R5', new \DateTimeImmutable('2025-03-15'));
        $this->createPhoto($this->alice, 'City lights', 'New York', 'Sony A7IV', new \DateTimeImmutable('2025-06-20'));
        $this->createPhoto($this->bob, 'Forest trail', 'Rocky Mountains', 'Nikon Z9', new \DateTimeImmutable('2024-08-10'));
    }

    public function testFilterByLocation(): void
    {
        $results = $this->photoRepository->findByFilters(['location' => 'Rocky']);
        $this->assertCount(2, $results);
    }

    public function testFilterByCamera(): void
    {
        $results = $this->photoRepository->findByFilters(['camera' => 'Canon']);
        $this->assertCount(1, $results);
        $this->assertSame('Mountain sunset', $results[0]->getDescription());
    }

    public function testFilterByDescription(): void
    {
        $results = $this->photoRepository->findByFilters(['description' => 'sunset']);
        $this->assertCount(1, $results);
        $this->assertSame('Mountain sunset', $results[0]->getDescription());
    }

    public function testFilterByUsername(): void
    {
        $results = $this->photoRepository->findByFilters(['username' => 'bob']);
        $this->assertCount(1, $results);
        $this->assertSame('Forest trail', $results[0]->getDescription());
    }

    public function testFilterByDateFrom(): void
    {
        $results = $this->photoRepository->findByFilters(['date_from' => '2025-01-01']);
        $this->assertCount(2, $results);
    }

    public function testFilterByDateTo(): void
    {
        $results = $this->photoRepository->findByFilters(['date_to' => '2024-12-31']);
        $this->assertCount(1, $results);
        $this->assertSame('Forest trail', $results[0]->getDescription());
    }

    public function testFilterByDateRange(): void
    {
        $results = $this->photoRepository->findByFilters(['date_from' => '2025-03-01', 'date_to' => '2025-04-01']);
        $this->assertCount(1, $results);
        $this->assertSame('Mountain sunset', $results[0]->getDescription());
    }

    public function testFilterCombinationLocationAndUsername(): void
    {
        $results = $this->photoRepository->findByFilters(['location' => 'Rocky', 'username' => 'alice']);
        $this->assertCount(1, $results);
        $this->assertSame('Mountain sunset', $results[0]->getDescription());
    }

    public function testEmptyFiltersReturnsAll(): void
    {
        $results = $this->photoRepository->findByFilters([]);
        $this->assertCount(3, $results);
    }

    public function testNoMatchReturnsEmpty(): void
    {
        $results = $this->photoRepository->findByFilters(['location' => 'Nonexistent']);
        $this->assertCount(0, $results);
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

    private function createPhoto(User $user, string $description, string $location, string $camera, \DateTimeImmutable $takenAt): Photo
    {
        $photo = new Photo();
        $photo->setDescription($description);
        $photo->setImageUrl('https://example.com/' . uniqid() . '.jpg');
        $photo->setLocation($location);
        $photo->setCamera($camera);
        $photo->setTakenAt($takenAt);
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
