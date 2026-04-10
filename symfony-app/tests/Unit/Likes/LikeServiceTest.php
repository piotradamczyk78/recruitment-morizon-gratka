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
     * Bug #14: catch-all Throwable wraps any exception into a generic Exception
     * with a hardcoded message, losing the original type and context.
     * Documents current (buggy) behavior.
     */
    public function testExecuteWrapsCreateLikeExceptionInGenericException(): void
    {
        $user = new User();
        $photo = new Photo();

        $this->repository
            ->method('createLike')
            ->willThrowException(new \RuntimeException('Database connection lost'));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Something went wrong while liking the photo');

        $this->service->execute($user, $photo);
    }

    /**
     * Bug #14: Even a TypeError gets wrapped into a generic Exception.
     */
    public function testExecuteWrapsCounterExceptionInGenericException(): void
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

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Something went wrong while liking the photo');

        $this->service->execute($user, $photo);
    }

    /**
     * Bug #14: Original exception message is lost - replaced with generic message.
     */
    public function testExecuteLosesOriginalExceptionMessage(): void
    {
        $user = new User();
        $photo = new Photo();
        $originalMessage = 'Unique constraint violation on likes table';

        $this->repository
            ->method('createLike')
            ->willThrowException(new \RuntimeException($originalMessage));

        try {
            $this->service->execute($user, $photo);
            $this->fail('Expected exception was not thrown');
        } catch (\Exception $e) {
            $this->assertNotSame($originalMessage, $e->getMessage());
            $this->assertSame('Something went wrong while liking the photo', $e->getMessage());
        }
    }
}
