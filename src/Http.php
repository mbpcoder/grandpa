<?php

declare(strict_types=1);

namespace Grandpa;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class Http
{
    private Client $client;
    private int $retries = 1;
    private int $retryDelayMs = 0;

    public function __construct(array $options = [])
    {
        $this->client = new Client(array_merge([
            'timeout' => 15,
        ], $options));
    }

    /**
     * Retry the next request up to $times attempts, waiting $delayMs between them.
     */
    public function retry(int $times, int $delayMs = 0): self
    {
        $this->retries = max(1, $times);
        $this->retryDelayMs = max(0, $delayMs);

        return $this;
    }

    public function get(string $url, array $options = []): string
    {
        return $this->request('GET', $url, $options);
    }

    public function post(string $url, array $options = []): string
    {
        return $this->request('POST', $url, $options);
    }

    public function request(string $method, string $url, array $options = []): string
    {
        $retries = $this->retries;
        $delayMs = $this->retryDelayMs;
        $this->retries = 1;
        $this->retryDelayMs = 0;

        for ($attempt = 1; $attempt <= $retries; $attempt++) {
            try {
                $response = $this->client->request($method, $url, $options);

                return (string) $response->getBody();
            } catch (GuzzleException $e) {
                $lastException = $e;

                if ($attempt < $retries && $delayMs > 0) {
                    usleep($delayMs * 1000);
                }
            }
        }

        throw new \RuntimeException("HTTP request failed: {$method} {$url}\n{$lastException->getMessage()}", 0, $lastException);
    }
}
