<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\InvalidPhoenixTokenException;
use App\Service\PhoenixApiClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class PhoenixApiClientTest extends TestCase
{
    public function testFetchPhotosReturnsPhotosOnSuccess(): void
    {
        $responseBody = json_encode([
            'photos' => [
                ['id' => 1, 'photo_url' => 'https://example.com/photo1.jpg'],
                ['id' => 2, 'photo_url' => 'https://example.com/photo2.jpg'],
            ],
        ]);

        $mockResponse = new MockResponse($responseBody, ['http_code' => 200]);
        $httpClient = new MockHttpClient($mockResponse);
        $client = new PhoenixApiClient($httpClient, 'http://phoenix:4000');

        $photos = $client->fetchPhotos('valid-token');

        $this->assertCount(2, $photos);
        $this->assertSame(1, $photos[0]['id']);
        $this->assertSame('https://example.com/photo1.jpg', $photos[0]['photo_url']);
    }

    public function testFetchPhotosReturnsEmptyArrayWhenNoPhotos(): void
    {
        $responseBody = json_encode(['photos' => []]);
        $mockResponse = new MockResponse($responseBody, ['http_code' => 200]);
        $httpClient = new MockHttpClient($mockResponse);
        $client = new PhoenixApiClient($httpClient, 'http://phoenix:4000');

        $photos = $client->fetchPhotos('valid-token');

        $this->assertSame([], $photos);
    }

    public function testFetchPhotosThrowsInvalidTokenExceptionOn401(): void
    {
        $mockResponse = new MockResponse('', ['http_code' => 401]);
        $httpClient = new MockHttpClient($mockResponse);
        $client = new PhoenixApiClient($httpClient, 'http://phoenix:4000');

        $this->expectException(InvalidPhoenixTokenException::class);
        $client->fetchPhotos('invalid-token');
    }

    public function testFetchPhotosThrowsRuntimeExceptionOnServerError(): void
    {
        $mockResponse = new MockResponse('', ['http_code' => 500]);
        $httpClient = new MockHttpClient($mockResponse);
        $client = new PhoenixApiClient($httpClient, 'http://phoenix:4000');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Phoenix API error: HTTP 500');
        $client->fetchPhotos('some-token');
    }

    public function testFetchPhotosSendsCorrectAccessTokenHeader(): void
    {
        $responseBody = json_encode(['photos' => []]);
        $mockResponse = new MockResponse($responseBody, ['http_code' => 200]);
        $httpClient = new MockHttpClient($mockResponse);
        $client = new PhoenixApiClient($httpClient, 'http://phoenix:4000');

        $client->fetchPhotos('my-secret-token');

        $requestOptions = $mockResponse->getRequestOptions();
        $this->assertSame('access-token: my-secret-token', $requestOptions['normalized_headers']['access-token'][0]);
    }

    public function testFetchPhotosUsesConfiguredUrl(): void
    {
        $responseBody = json_encode(['photos' => []]);
        $mockResponse = new MockResponse($responseBody, ['http_code' => 200]);
        $httpClient = new MockHttpClient($mockResponse);
        $client = new PhoenixApiClient($httpClient, 'http://custom-host:9000');

        $client->fetchPhotos('token');

        $this->assertSame('http://custom-host:9000/api/photos', $mockResponse->getRequestUrl());
    }

    public function testFetchPhotosHandlesMissingPhotosKey(): void
    {
        $responseBody = json_encode(['data' => 'something']);
        $mockResponse = new MockResponse($responseBody, ['http_code' => 200]);
        $httpClient = new MockHttpClient($mockResponse);
        $client = new PhoenixApiClient($httpClient, 'http://phoenix:4000');

        $photos = $client->fetchPhotos('token');

        $this->assertSame([], $photos);
    }
}
