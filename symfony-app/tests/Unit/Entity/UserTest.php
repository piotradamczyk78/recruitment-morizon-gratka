<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Photo;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class UserTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        $this->user = new User();
    }

    public function testGetIdReturnsNullForNewEntity(): void
    {
        $this->assertNull($this->user->getId());
    }

    public function testSetAndGetUsername(): void
    {
        $this->user->setUsername('john_doe');
        $this->assertSame('john_doe', $this->user->getUsername());
    }

    public function testSetAndGetEmail(): void
    {
        $this->user->setEmail('john@example.com');
        $this->assertSame('john@example.com', $this->user->getEmail());
    }

    public function testSetAndGetBio(): void
    {
        $this->user->setBio('Nature photographer');
        $this->assertSame('Nature photographer', $this->user->getBio());
    }

    public function testNewUserHasEmptyPhotosCollection(): void
    {
        $this->assertCount(0, $this->user->getPhotos());
    }

    public function testAddPhoto(): void
    {
        $photo = new Photo();
        $this->user->addPhoto($photo);

        $this->assertCount(1, $this->user->getPhotos());
        $this->assertTrue($this->user->getPhotos()->contains($photo));
        $this->assertSame($this->user, $photo->getUser());
    }

    public function testAddSamePhotoTwiceDoesNotDuplicate(): void
    {
        $photo = new Photo();
        $this->user->addPhoto($photo);
        $this->user->addPhoto($photo);

        $this->assertCount(1, $this->user->getPhotos());
    }

    /**
     * Fix #16: removePhoto() no longer calls setUser(null).
     * It only removes the photo from the collection.
     */
    public function testRemovePhoto(): void
    {
        $photo = new Photo();
        $this->user->addPhoto($photo);

        $this->user->removePhoto($photo);
        $this->assertCount(0, $this->user->getPhotos());
    }
}
