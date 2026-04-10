<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Photo;
use App\Entity\User;
use App\Likes\LikeRepositoryInterface;
use App\Likes\LikeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PhotoController extends AbstractController
{
    public function __construct(
        private LikeRepositoryInterface $likeRepository,
        private LikeService $likeService,
    ) {}

    #[Route('/photo/{id}/like', name: 'photo_like', methods: ['POST'])]
    public function like($id, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('like-photo-' . $id, $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('home');
        }

        $session = $request->getSession();
        $userId = $session->get('user_id');

        if (!$userId) {
            $this->addFlash('error', 'You must be logged in to like photos.');
            return $this->redirectToRoute('home');
        }

        $user = $em->getRepository(User::class)->find($userId);

        if (!$user) {
            $session->remove('user_id');
            $this->addFlash('error', 'User session expired. Please log in again.');
            return $this->redirectToRoute('home');
        }

        $photo = $em->getRepository(Photo::class)->find($id);

        if (!$photo) {
            throw $this->createNotFoundException('Photo not found');
        }

        if ($this->likeRepository->hasUserLikedPhoto($user, $photo)) {
            $this->likeRepository->unlikePhoto($user, $photo);
            $this->addFlash('info', 'Photo unliked!');
        } else {
            $this->likeService->execute($user, $photo);
            $this->addFlash('success', 'Photo liked!');
        }

        return $this->redirectToRoute('home');
    }
}
