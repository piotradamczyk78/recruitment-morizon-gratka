<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\AuthToken;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class AuthTokenTest extends TestCase
{
    private AuthToken $token;

    protected function setUp(): void
    {
        $this->token = new AuthToken();
    }

    public function testGetIdReturnsNullForNewEntity(): void
    {
        $this->assertNull($this->token->getId());
    }

    public function testSetAndGetToken(): void
    {
        $this->token->setToken('abc123def456');
        $this->assertSame('abc123def456', $this->token->getToken());
    }

    public function testSetAndGetUser(): void
    {
        $user = new User();
        $this->token->setUser($user);
        $this->assertSame($user, $this->token->getUser());
    }

    public function testCreatedAtIsSetAutomaticallyInConstructor(): void
    {
        $before = new \DateTime();
        $token = new AuthToken();
        $after = new \DateTime();

        $this->assertGreaterThanOrEqual($before, $token->getCreatedAt());
        $this->assertLessThanOrEqual($after, $token->getCreatedAt());
    }

    public function testSetAndGetCreatedAt(): void
    {
        $date = new \DateTime('2024-01-15');
        $this->token->setCreatedAt($date);
        $this->assertSame($date, $this->token->getCreatedAt());
    }

    public function testSettersReturnSelfForChaining(): void
    {
        $result = $this->token->setToken('test');
        $this->assertSame($this->token, $result);

        $result = $this->token->setCreatedAt(new \DateTime());
        $this->assertSame($this->token, $result);
    }
}
