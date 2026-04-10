<?php
declare(strict_types=1);

namespace App\Likes;

use App\Entity\Photo;

interface LikeRepositoryInterface
{
    public function unlikePhoto(Photo $photo): void;

    public function hasUserLikedPhoto(Photo $photo): bool;

    public function createLike(Photo $photo): Like;

    public function updatePhotoCounter(Photo $photo, int $increment): void;

    /**
     * @param Photo[] $photos
     * @return array<int, bool> Map of photo ID => liked status
     */
    public function getUserLikedPhotoIds(array $photos): array;
}