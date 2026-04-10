<?php

declare(strict_types=1);

namespace App\Likes;

use App\Entity\Photo;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class LikeRepository extends ServiceEntityRepository implements LikeRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Like::class);
    }

    #[\Override]
    public function unlikePhoto(User $user, Photo $photo): void
    {
        $em = $this->getEntityManager();

        $like = $em->createQueryBuilder()
            ->select('l')
            ->from(Like::class, 'l')
            ->where('l.user = :user')
            ->andWhere('l.photo = :photo')
            ->setParameter('user', $user)
            ->setParameter('photo', $photo)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($like) {
            $em->wrapInTransaction(function () use ($em, $like, $photo): void {
                $em->remove($like);
                $em->flush();

                $em->getConnection()->executeStatement(
                    'UPDATE photos SET like_counter = like_counter - 1 WHERE id = ?',
                    [$photo->getId()]
                );
            });
            $em->refresh($photo);
        }
    }

    #[\Override]
    public function hasUserLikedPhoto(User $user, Photo $photo): bool
    {
        $result = $this->createQueryBuilder('l')
            ->select('l.id')
            ->where('l.user = :user')
            ->andWhere('l.photo = :photo')
            ->setParameter('user', $user)
            ->setParameter('photo', $photo)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result !== null;
    }

    #[\Override]
    public function createLike(User $user, Photo $photo): Like
    {
        $like = new Like();
        $like->setUser($user);
        $like->setPhoto($photo);

        $em = $this->getEntityManager();
        $em->persist($like);
        $em->flush();

        return $like;
    }

    #[\Override]
    public function getUserLikedPhotoIds(User $user, array $photos): array
    {
        if (empty($photos)) {
            return [];
        }

        $photoIds = array_map(fn(Photo $p) => $p->getId(), $photos);

        $likedIds = $this->createQueryBuilder('l')
            ->select('IDENTITY(l.photo) AS photo_id')
            ->where('l.user = :user')
            ->andWhere('l.photo IN (:photos)')
            ->setParameter('user', $user)
            ->setParameter('photos', $photoIds)
            ->getQuery()
            ->getSingleColumnResult();

        $result = [];
        foreach ($photos as $photo) {
            $result[$photo->getId()] = in_array($photo->getId(), $likedIds, false);
        }

        return $result;
    }

    #[\Override]
    public function updatePhotoCounter(Photo $photo, int $increment): void
    {
        $em = $this->getEntityManager();
        $em->getConnection()->executeStatement(
            'UPDATE photos SET like_counter = like_counter + ? WHERE id = ?',
            [$increment, $photo->getId()]
        );
        $em->refresh($photo);
    }
}
