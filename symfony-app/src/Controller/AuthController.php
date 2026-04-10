<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\AuthTokenRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AuthController extends AbstractController
{
    public function __construct(
        private AuthTokenRepository $authTokenRepository,
    ) {}

    #[Route('/auth/login', name: 'auth_login', methods: ['POST'])]
    public function login(Request $request): Response
    {
        $token = $request->request->get('token', '');
        $username = $request->request->get('username', '');

        $user = $this->authTokenRepository->findUserByTokenAndUsername($token, $username);

        if (!$user) {
            return new Response('Invalid credentials', 401);
        }

        $session = $request->getSession();
        $session->set('user_id', $user->getId());
        $session->set('username', $username);

        $this->addFlash('success', 'Welcome back, ' . $username . '!');

        return $this->redirectToRoute('home');
    }

    #[Route('/logout', name: 'logout')]
    public function logout(Request $request): Response
    {
        $session = $request->getSession();
        $session->clear();

        $this->addFlash('info', 'You have been logged out successfully.');

        return $this->redirectToRoute('home');
    }
}
