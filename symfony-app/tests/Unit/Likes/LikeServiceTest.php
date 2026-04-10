<?php

declare(strict_types=1);

namespace App\Tests\Unit\Likes;

use App\Entity\Photo;
use App\Entity\User;
use App\Likes\Like;
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

    public function testToggleLikeCreatesLikeWhenNotLiked(): void
    {
        $user = new User();
        $photo = new Photo();

        $this->repository
            ->method('hasUserLikedPhoto')
            ->with($user, $photo)
            ->willReturn(false);

        $this->repository
            ->expects($this->once())
            ->method('createLike')
            ->with($user, $photo);

        $result = $this->service->toggleLike($user, $photo);
        $this->assertTrue($result);
    }

    public function testToggleLikeUnlikesWhenAlreadyLiked(): void
    {
        $user = new User();
        $photo = new Photo();

        $this->repository
            ->method('hasUserLikedPhoto')
            ->with($user, $photo)
            ->willReturn(true);

        $this->repository
            ->expects($this->once())
            ->method('unlikePhoto')
            ->with($user, $photo);

        $this->repository
            ->expects($this->never())
            ->method('createLike');

        $result = $this->service->toggleLike($user, $photo);
        $this->assertFalse($result);
    }

    public function testToggleLikePropagatesException(): void
    {
        $user = new User();
        $photo = new Photo();

        $this->repository
            ->method('hasUserLikedPhoto')
            ->willReturn(false);

        $this->repository
            ->method('createLike')
            ->willThrowException(new \RuntimeException('Database connection lost'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Database connection lost');

        $this->service->toggleLike($user, $photo);
    }
}
