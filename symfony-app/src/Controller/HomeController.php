<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Likes\LikeRepositoryInterface;
use App\Repository\PhotoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    public function __construct(
        private PhotoRepository $photoRepository,
        private LikeRepositoryInterface $likeRepository,
    ) {}

    #[Route('/', name: 'home')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $filters = [
            'location' => $request->query->get('location', ''),
            'camera' => $request->query->get('camera', ''),
            'description' => $request->query->get('description', ''),
            'username' => $request->query->get('username', ''),
            'date_from' => $request->query->get('date_from', ''),
            'date_to' => $request->query->get('date_to', ''),
        ];

        $hasFilters = array_filter($filters, fn(string $v) => $v !== '');
        $photos = $hasFilters
            ? $this->photoRepository->findByFilters($filters)
            : $this->photoRepository->findAllWithUsers();

        $session = $request->getSession();
        $userId = $session->get('user_id');
        $currentUser = null;
        $userLikes = [];

        if ($userId) {
            $currentUser = $em->getRepository(User::class)->find($userId);

            if ($currentUser) {
                $userLikes = $this->likeRepository->getUserLikedPhotoIds($currentUser, $photos);
            }
        }

        return $this->render('home/index.html.twig', [
            'photos' => $photos,
            'currentUser' => $currentUser,
            'userLikes' => $userLikes,
            'filters' => $filters,
        ]);
    }
}
