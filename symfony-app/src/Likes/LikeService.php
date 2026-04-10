<?php

declare(strict_types=1);

namespace App\Likes;

use App\Entity\Photo;
use App\Entity\User;

class LikeService
{
    public function __construct(
        private LikeRepositoryInterface $likeRepository
    ) {}

    /**
     * @return bool true if liked, false if unliked
     */
    public function toggleLike(User $user, Photo $photo): bool
    {
        if ($this->likeRepository->hasUserLikedPhoto($user, $photo)) {
            $this->likeRepository->unlikePhoto($user, $photo);
            return false;
        }

        $this->likeRepository->createLike($user, $photo);
        return true;
    }
}
