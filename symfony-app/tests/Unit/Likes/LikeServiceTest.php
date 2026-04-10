<?php

declare(strict_types=1);

namespace App\Tests\Unit\Likes;

use App\Entity\Photo;
use App\Entity\User;
use App\Likes\LikeRepositoryInterface;
use App\Likes\LikeService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class LikeServiceTest extends TestCase
{
    private LikeRepositoryInterface&MockObject $repository;
    private LikeService $service;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(LikeRepositoryInterface::class);
        $this->service = new LikeService($this->repository);
    }

    public function testExecuteCreatesLikeAndUpdatesCounter(): void
    {
        $user = new User();
        $photo = new Photo();

        $this->repository
            ->expects($this->once())
            ->method('createLike')
            ->with($user, $photo);

        $this->repository
            ->expects($this->once())
            ->method('updatePhotoCounter')
            ->with($photo, 1);

        $this->service->execute($user, $photo);
    }

    /**
     * Fix #14: Exceptions from createLike propagate with original type and message.
     */
    public function testExecutePropagatesCreateLikeException(): void
    {
        $user = new User();
        $photo = new Photo();

        $this->repository
            ->method('createLike')
            ->willThrowException(new \RuntimeException('Database connection lost'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Database connection lost');

        $this->service->execute($user, $photo);
    }

    /**
     * Fix #14: Exceptions from updatePhotoCounter propagate with original type.
     */
    public function testExecutePropagatesCounterException(): void
    {
        $user = new User();
        $photo = new Photo();

        $like = $this->createMock(\App\Likes\Like::class);

        $this->repository
            ->method('createLike')
            ->willReturn($like);

        $this->repository
            ->method('updatePhotoCounter')
            ->willThrowException(new \TypeError('Type mismatch'));

        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Type mismatch');

        $this->service->execute($user, $photo);
    }
}
