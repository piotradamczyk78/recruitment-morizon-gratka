<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AuthController extends AbstractController
{
    #[Route('/auth/login', name: 'auth_login', methods: ['POST'])]
    public function login(Connection $connection, Request $request): Response
    {
        $token = $request->request->get('token', '');
        $username = $request->request->get('username', '');

        $sql = "SELECT * FROM auth_tokens WHERE token = ?";
        $result = $connection->executeQuery($sql, [$token]);
        $tokenData = $result->fetchAssociative();

        if (!$tokenData) {
            return new Response('Invalid token', 401);
        }

        $userSql = "SELECT * FROM users WHERE username = ?";
        $userResult = $connection->executeQuery($userSql, [$username]);
        $userData = $userResult->fetchAssociative();

        if (!$userData) {
            return new Response('User not found', 404);
        }

        $session = $request->getSession();
        $session->set('user_id', $userData['id']);
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
