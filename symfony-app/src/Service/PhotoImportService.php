<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Photo;
use App\Entity\User;
use App\Repository\PhotoRepository;
use Doctrine\ORM\EntityManagerInterface;

class PhotoImportService
{
    public function __construct(
        private PhoenixApiClient $phoenixApiClient,
        private EntityManagerInterface $em,
        private PhotoRepository $photoRepository,
    ) {
    }

    /**
     * @return array{imported: int, skipped: int}
     */
    public function importPhotos(User $user, string $token): array
    {
        $phoenixPhotos = $this->phoenixApiClient->fetchPhotos($token);

        $existingUrls = $this->getExistingPhotoUrls($user);

        $imported = 0;
        $skipped = 0;

        foreach ($phoenixPhotos as $phoenixPhoto) {
            $url = $phoenixPhoto['photo_url'] ?? null;

            if ($url === null || $url === '') {
                $skipped++;
                continue;
            }

            if (in_array($url, $existingUrls, true)) {
                $skipped++;
                continue;
            }

            $photo = new Photo();
            $photo->setImageUrl($url);
            $photo->setUser($user);
            $this->em->persist($photo);

            $existingUrls[] = $url;
            $imported++;
        }

        if ($imported > 0) {
            $this->em->flush();
        }

        return ['imported' => $imported, 'skipped' => $skipped];
    }

    /**
     * @return string[]
     */
    private function getExistingPhotoUrls(User $user): array
    {
        return $this->photoRepository->createQueryBuilder('p')
            ->select('p.imageUrl')
            ->where('p.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleColumnResult();
    }
}
