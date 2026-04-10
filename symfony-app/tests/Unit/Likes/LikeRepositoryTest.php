<?php

declare(strict_types=1);

namespace App\Tests\Unit\Likes;

use App\Likes\LikeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
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
     * Fix #12: Repository no longer stores user as instance state.
     * User is passed as a parameter to each method, making it stateless.
     */
    public function testRepositoryHasNoUserProperty(): void
    {
        $reflection = new \ReflectionClass($this->repository);

        $this->assertFalse(
            $reflection->hasProperty('user'),
            'LikeRepository should not have a user property - user should be passed as method parameter'
        );
    }

    public function testRepositoryHasNoSetUserMethod(): void
    {
        $this->assertFalse(
            method_exists($this->repository, 'setUser'),
            'LikeRepository should not have setUser() - user should be passed as method parameter'
        );
    }
}
