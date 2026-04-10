<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class PhoenixApiClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $phoenixApiUrl = 'http://phoenix:4000',
    ) {
    }

    /**
     * @return array<int, array{id: int, photo_url: string}>
     */
    public function fetchPhotos(string $token): array
    {
        $response = $this->httpClient->request('GET', $this->phoenixApiUrl . '/api/photos', [
            'headers' => [
                'access-token' => $token,
            ],
        ]);

        $statusCode = $response->getStatusCode();

        if ($statusCode === 401) {
            throw new InvalidPhoenixTokenException();
        }

        if ($statusCode !== 200) {
            throw new \RuntimeException('Phoenix API error: HTTP ' . $statusCode);
        }

        $data = $response->toArray();

        return $data['photos'] ?? [];
    }
}
