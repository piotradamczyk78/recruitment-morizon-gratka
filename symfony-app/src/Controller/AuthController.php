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

        $sql = "SELECT u.* FROM users u
            INNER JOIN auth_tokens t ON t.user_id = u.id
            WHERE t.token = ? AND u.username = ?";
        $result = $connection->executeQuery($sql, [$token, $username]);
        $userData = $result->fetchAssociative();

        if (!$userData) {
            return new Response('Invalid credentials', 401);
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
