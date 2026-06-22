<?php

declare(strict_types=1);

namespace Grandpa;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

class Http
{
    private Client $client;

    public function __construct(array $options = [])
    {
        $this->client = new Client(array_merge([
            'timeout' => 15,
        ], $options));
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
        try {
            $response = $this->client->request($method, $url, $options);
        } catch (GuzzleException $e) {
            throw new \RuntimeException("HTTP request failed: {$method} {$url}\n{$e->getMessage()}", 0, $e);
        }

        return (string) $response->getBody();
    }
}
