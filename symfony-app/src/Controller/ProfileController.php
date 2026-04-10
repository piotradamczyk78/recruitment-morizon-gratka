<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Service\InvalidPhoenixTokenException;
use App\Service\PhotoImportService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'profile')]
    public function profile(Request $request, EntityManagerInterface $em): Response
    {
        $session = $request->getSession();
        $userId = $session->get('user_id');

        if (!$userId) {
            return $this->redirectToRoute('home');
        }

        $user = $em->getRepository(User::class)->find($userId);

        if (!$user) {
            $session->clear();
            return $this->redirectToRoute('home');
        }

        return $this->render('profile/index.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/profile/phoenix-token', name: 'profile_phoenix_token', methods: ['POST'])]
    public function savePhoenixToken(Request $request, EntityManagerInterface $em): Response
    {
        $session = $request->getSession();
        $userId = $session->get('user_id');

        if (!$userId) {
            return $this->redirectToRoute('home');
        }

        if (!$this->isCsrfTokenValid('phoenix-token', $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('profile');
        }

        $user = $em->getRepository(User::class)->find($userId);

        if (!$user) {
            $session->clear();
            return $this->redirectToRoute('home');
        }

        $token = $request->request->get('phoenix_api_token', '');
        $user->setPhoenixApiToken($token !== '' ? $token : null);
        $em->flush();

        $this->addFlash('success', 'Phoenix API token saved.');
        return $this->redirectToRoute('profile');
    }

    #[Route('/profile/import-photos', name: 'profile_import_photos', methods: ['POST'])]
    public function importPhotos(Request $request, EntityManagerInterface $em, PhotoImportService $importService): Response
    {
        $session = $request->getSession();
        $userId = $session->get('user_id');

        if (!$userId) {
            return $this->redirectToRoute('home');
        }

        if (!$this->isCsrfTokenValid('import-photos', $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid CSRF token.');
            return $this->redirectToRoute('profile');
        }

        $user = $em->getRepository(User::class)->find($userId);

        if (!$user) {
            $session->clear();
            return $this->redirectToRoute('home');
        }

        $token = $user->getPhoenixApiToken();

        if (!$token) {
            $this->addFlash('error', 'Set your Phoenix API token first.');
            return $this->redirectToRoute('profile');
        }

        try {
            $result = $importService->importPhotos($user, $token);
            $this->addFlash('success', sprintf('Imported %d photos (%d skipped).', $result['imported'], $result['skipped']));
        } catch (InvalidPhoenixTokenException) {
            $this->addFlash('error', 'Invalid Phoenix API token.');
        } catch (\RuntimeException $e) {
            $this->addFlash('error', 'Failed to connect to Phoenix API.');
        }

        return $this->redirectToRoute('profile');
    }
}
