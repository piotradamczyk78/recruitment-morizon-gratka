<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Photo;
use App\Entity\User;
use App\Repository\PhotoRepository;
use App\Service\PhoenixApiClient;
use App\Service\PhotoImportService;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PhotoImportServiceTest extends TestCase
{
    private PhoenixApiClient&MockObject $apiClient;
    private EntityManagerInterface&MockObject $em;
    private PhotoRepository&MockObject $photoRepository;
    private PhotoImportService $service;

    protected function setUp(): void
    {
        $this->apiClient = $this->createMock(PhoenixApiClient::class);
        $this->em = $this->createMock(EntityManagerInterface::class);
        $this->photoRepository = $this->createMock(PhotoRepository::class);
        $this->service = new PhotoImportService(
            $this->apiClient,
            $this->em,
            $this->photoRepository,
        );
    }

    public function testImportNewPhotos(): void
    {
        $user = new User();
        $user->setUsername('testuser');
        $user->setEmail('test@example.com');

        $this->apiClient->method('fetchPhotos')
            ->with('token123')
            ->willReturn([
                ['id' => 1, 'photo_url' => 'https://example.com/photo1.jpg'],
                ['id' => 2, 'photo_url' => 'https://example.com/photo2.jpg'],
            ]);

        $this->mockExistingUrls([]);

        $this->em->expects($this->exactly(2))->method('persist');
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->importPhotos($user, 'token123');

        $this->assertSame(2, $result['imported']);
        $this->assertSame(0, $result['skipped']);
    }

    public function testSkipsDuplicatePhotos(): void
    {
        $user = new User();
        $user->setUsername('testuser');
        $user->setEmail('test@example.com');

        $this->apiClient->method('fetchPhotos')
            ->willReturn([
                ['id' => 1, 'photo_url' => 'https://example.com/existing.jpg'],
                ['id' => 2, 'photo_url' => 'https://example.com/new.jpg'],
            ]);

        $this->mockExistingUrls(['https://example.com/existing.jpg']);

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->importPhotos($user, 'token');

        $this->assertSame(1, $result['imported']);
        $this->assertSame(1, $result['skipped']);
    }

    public function testSkipsAllDuplicates(): void
    {
        $user = new User();
        $user->setUsername('testuser');
        $user->setEmail('test@example.com');

        $this->apiClient->method('fetchPhotos')
            ->willReturn([
                ['id' => 1, 'photo_url' => 'https://example.com/existing.jpg'],
            ]);

        $this->mockExistingUrls(['https://example.com/existing.jpg']);

        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->never())->method('flush');

        $result = $this->service->importPhotos($user, 'token');

        $this->assertSame(0, $result['imported']);
        $this->assertSame(1, $result['skipped']);
    }

    public function testHandlesEmptyPhoenixResponse(): void
    {
        $user = new User();
        $user->setUsername('testuser');
        $user->setEmail('test@example.com');

        $this->apiClient->method('fetchPhotos')->willReturn([]);

        $this->mockExistingUrls([]);

        $this->em->expects($this->never())->method('persist');
        $this->em->expects($this->never())->method('flush');

        $result = $this->service->importPhotos($user, 'token');

        $this->assertSame(0, $result['imported']);
        $this->assertSame(0, $result['skipped']);
    }

    public function testSkipsPhotosWithEmptyUrl(): void
    {
        $user = new User();
        $user->setUsername('testuser');
        $user->setEmail('test@example.com');

        $this->apiClient->method('fetchPhotos')
            ->willReturn([
                ['id' => 1, 'photo_url' => ''],
                ['id' => 2, 'photo_url' => 'https://example.com/valid.jpg'],
                ['id' => 3],
            ]);

        $this->mockExistingUrls([]);

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->importPhotos($user, 'token');

        $this->assertSame(1, $result['imported']);
        $this->assertSame(2, $result['skipped']);
    }

    public function testDeduplicatesWithinSameBatch(): void
    {
        $user = new User();
        $user->setUsername('testuser');
        $user->setEmail('test@example.com');

        $this->apiClient->method('fetchPhotos')
            ->willReturn([
                ['id' => 1, 'photo_url' => 'https://example.com/same.jpg'],
                ['id' => 2, 'photo_url' => 'https://example.com/same.jpg'],
            ]);

        $this->mockExistingUrls([]);

        $this->em->expects($this->once())->method('persist');
        $this->em->expects($this->once())->method('flush');

        $result = $this->service->importPhotos($user, 'token');

        $this->assertSame(1, $result['imported']);
        $this->assertSame(1, $result['skipped']);
    }

    private function mockExistingUrls(array $urls): void
    {
        $query = $this->createMock(AbstractQuery::class);
        $query->method('getSingleColumnResult')->willReturn($urls);

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $this->photoRepository->method('createQueryBuilder')->willReturn($qb);
    }
}
