<?php
declare(strict_types=1);

namespace App\Likes;

use App\Entity\Photo;
use App\Entity\User;

interface LikeRepositoryInterface
{
    public function unlikePhoto(User $user, Photo $photo): void;

    public function hasUserLikedPhoto(User $user, Photo $photo): bool;

    public function createLike(User $user, Photo $photo): Like;

    /**
     * @param Photo[] $photos
     * @return array<int, bool> Map of photo ID => liked status
     */
    public function getUserLikedPhotoIds(User $user, array $photos): array;
}