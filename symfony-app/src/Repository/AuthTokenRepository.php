<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AuthToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

final class AuthTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuthToken::class);
    }

    public function findUserByTokenAndUsername(string $token, string $username): ?User
    {
        $authToken = $this->createQueryBuilder('t')
            ->join('t.user', 'u')
            ->where('t.token = :token')
            ->andWhere('u.username = :username')
            ->setParameter('token', $token)
            ->setParameter('username', $username)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $authToken?->getUser();
    }
}
