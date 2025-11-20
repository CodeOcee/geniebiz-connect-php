<?php

namespace GenieBusinessConnect;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\GuzzleException;
use JsonException;

/**
 * Genie Business Connect API Client
 * Updated for Guzzle 7+ and PHP 8.2+ compatibility
 */
class Client
{
    const API_VERSION     = '2.0';
    const APP_VERSION     = 'geniebiz-connect-php';
    const SIGN_METHOD     = 'sha1';
    const SANDBOX_ENDPOINT     = 'https://api.uat.geniebiz.lk/public/';
    const PRODUCTION_ENDPOINT  = 'https://api.geniebiz.lk/public/';

    private GuzzleClient $httpClient;
    private string $apiKey;
    private string $appId;
    private string $mode;
    private array $clientOptions;
    private string $baseUrl;

    public Transactions $transactions;

    public function __construct(string $apiKey, string $appId, string $mode = 'production', array $clientOptions = [])
    {
        $this->apiKey        = $apiKey;
        $this->appId         = $appId;
        $this->mode          = $mode;
        $this->baseUrl       = ($mode === 'production') ? self::PRODUCTION_ENDPOINT : self::SANDBOX_ENDPOINT;
        $this->clientOptions = $clientOptions;

        $this->initiateHttpClient();

        $this->transactions = new Transactions($this);
    }

    public function setClient(GuzzleClient $client): void
    {
        $this->httpClient = $client;
    }

    private function initiateHttpClient(): void
    {
        $options = [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
                'Authorization' => $this->apiKey,
            ],
            'timeout' => 30,
        ];

        $this->httpClient = new GuzzleClient(array_replace_recursive($this->clientOptions, $options));
    }

    private function buildBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Handle API response (Guzzle 7+ compatible)
     */
    private function handleResponse(Response $response): object
    {
        $body = $response->getBody()->getContents();

        try {
            $data = json_decode($body, false, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new \RuntimeException('Invalid JSON response from Genie API: ' . $e->getMessage(), 0, $e);
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('JSON decode error: ' . json_last_error_msg());
        }

        return $data;
    }

    /**
     * POST request
     */
    public function post(string $endpoint, array $json): object
    {
        $json['apiVersion'] = self::API_VERSION;
        $json['appVersion'] = self::APP_VERSION;
        $json['signMethod'] = self::SIGN_METHOD;

        try {
            $response = $this->httpClient->request('POST', $this->buildBaseUrl() . $endpoint, [
                'json' => $json
            ]);
        } catch (GuzzleException $e) {
            throw new \RuntimeException('Request failed: ' . $e->getMessage(), 0, $e);
        }

        return $this->handleResponse($response);
    }

    /**
     * GET request with optional pagination
     */
    public function get(string $endpoint, array $pagination = []): object
    {
        $url = $this->applyPagination($this->buildBaseUrl() . $endpoint, $pagination);

        try {
            $response = $this->httpClient->request('GET', $url);
        } catch (GuzzleException $e) {
            throw new \RuntimeException('Request failed: ' . $e->getMessage(), 0, $e);
        }

        return $this->handleResponse($response);
    }

    private function applyPagination(string $url, array $pagination): string
    {
        if (empty($pagination)) {
            return $url;
        }

        $allowed = ['page'];
        $clean   = array_intersect_key($pagination, array_flip($allowed));

        return $url . '?' . http_build_query($clean);
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }
}
