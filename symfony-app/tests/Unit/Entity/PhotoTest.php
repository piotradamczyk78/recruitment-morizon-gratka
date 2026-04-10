<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Photo;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class PhotoTest extends TestCase
{
    private Photo $photo;

    protected function setUp(): void
    {
        $this->photo = new Photo();
    }

    public function testNewPhotoHasZeroLikeCounter(): void
    {
        $this->assertSame(0, $this->photo->getLikeCounter());
    }

    public function testGetIdReturnsNullForNewEntity(): void
    {
        $this->assertNull($this->photo->getId());
    }

    public function testSetAndGetDescription(): void
    {
        $this->photo->setDescription('A beautiful sunset');
        $this->assertSame('A beautiful sunset', $this->photo->getDescription());
    }

    public function testSetAndGetImageUrl(): void
    {
        $this->photo->setImageUrl('https://example.com/photo.jpg');
        $this->assertSame('https://example.com/photo.jpg', $this->photo->getImageUrl());
    }

    public function testSetAndGetCamera(): void
    {
        $this->photo->setCamera('Canon EOS R5');
        $this->assertSame('Canon EOS R5', $this->photo->getCamera());
    }

    public function testSetAndGetLocation(): void
    {
        $this->photo->setLocation('Rocky Mountains');
        $this->assertSame('Rocky Mountains', $this->photo->getLocation());
    }

    public function testSetAndGetTakenAt(): void
    {
        $date = new \DateTimeImmutable('2024-06-15');
        $this->photo->setTakenAt($date);
        $this->assertSame($date, $this->photo->getTakenAt());
    }

    public function testSetAndGetUser(): void
    {
        $user = new User();
        $this->photo->setUser($user);
        $this->assertSame($user, $this->photo->getUser());
    }

    /**
     * Bug #16: setUser() accepts null despite property being typed as non-nullable User.
     * Calling setUser(null) causes a TypeError at runtime.
     * Documents current (buggy) behavior - the method signature allows null
     * but the property type rejects it.
     */
    public function testSetUserWithNullThrowsTypeError(): void
    {
        $user = new User();
        $this->photo->setUser($user);

        $this->expectException(\TypeError::class);
        $this->photo->setUser(null);
    }

    /**
     * Bug #23: setLikeCounter() is public - allows arbitrary counter manipulation.
     * Documents current (buggy) behavior.
     */
    public function testSetLikeCounterIsPublic(): void
    {
        $this->photo->setLikeCounter(999);
        $this->assertSame(999, $this->photo->getLikeCounter());
    }

    public function testSetLikeCounterToNegativeValue(): void
    {
        $this->photo->setLikeCounter(-5);
        $this->assertSame(-5, $this->photo->getLikeCounter());
    }

    public function testSettersReturnSelfForChaining(): void
    {
        $result = $this->photo->setDescription('test');
        $this->assertSame($this->photo, $result);

        $result = $this->photo->setImageUrl('https://example.com/img.jpg');
        $this->assertSame($this->photo, $result);

        $result = $this->photo->setLocation('test');
        $this->assertSame($this->photo, $result);

        $result = $this->photo->setCamera('test');
        $this->assertSame($this->photo, $result);

        $result = $this->photo->setLikeCounter(0);
        $this->assertSame($this->photo, $result);
    }

    public function testNullableFieldsDefaultToNull(): void
    {
        $this->assertNull($this->photo->getDescription());
        $this->assertNull($this->photo->getLocation());
        $this->assertNull($this->photo->getCamera());
        $this->assertNull($this->photo->getTakenAt());
    }
}
