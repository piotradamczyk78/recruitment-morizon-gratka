<?php

declare(strict_types=1);

namespace App\Tests\Unit\Likes;

use App\Entity\User;
use App\Likes\LikeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class LikeRepositoryTest extends TestCase
{
    private LikeRepository $repository;

    protected function setUp(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);

        $classMetadata = new ClassMetadata('App\Likes\Like');

        $em->method('getClassMetadata')
            ->willReturn($classMetadata);

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')
            ->willReturn($em);

        $this->repository = new LikeRepository($registry);
    }

    /**
     * Bug #12: setUser() on a singleton - stateful repository.
     * Documents current (buggy) behavior where the user is stored
     * as instance state on a shared service. In Symfony, repositories
     * are singletons, so this state leaks between requests/callers.
     */
    public function testSetUserStoresUserAsInstanceState(): void
    {
        $user1 = new User();
        $this->repository->setUser($user1);

        $user2 = new User();
        $this->repository->setUser($user2);

        $reflection = new \ReflectionClass($this->repository);
        $property = $reflection->getProperty('user');
        $property->setAccessible(true);

        $this->assertSame($user2, $property->getValue($this->repository));
    }

    public function testSetUserAcceptsNull(): void
    {
        $user = new User();
        $this->repository->setUser($user);
        $this->repository->setUser(null);

        $reflection = new \ReflectionClass($this->repository);
        $property = $reflection->getProperty('user');
        $property->setAccessible(true);

        $this->assertNull($property->getValue($this->repository));
    }

    /**
     * Bug #12: Demonstrates that the state persists across multiple
     * setUser() calls - if repository is a singleton, user A's state
     * could leak into user B's request.
     */
    public function testStatefulRepositoryRetainsUserBetweenCalls(): void
    {
        $userA = new User();
        $userB = new User();

        $reflection = new \ReflectionClass($this->repository);
        $property = $reflection->getProperty('user');
        $property->setAccessible(true);

        $this->repository->setUser($userA);
        $this->assertSame($userA, $property->getValue($this->repository));

        // Without explicit reset, userA is still stored
        $this->assertSame($userA, $property->getValue($this->repository));

        $this->repository->setUser($userB);
        // userA is gone, userB took its place
        $this->assertNotSame($userA, $property->getValue($this->repository));
        $this->assertSame($userB, $property->getValue($this->repository));
    }
}
