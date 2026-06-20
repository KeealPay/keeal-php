<?php

declare(strict_types=1);

namespace Keeal\Checkout\Tests;

use Keeal\Checkout\HttpTransportInterface;

final class FakeHttpTransport implements HttpTransportInterface
{
    /** @var list<array{method: string, url: string, body: ?string, headers: list<string>}> */
    public array $requests = [];

    /**
     * @param list<array{status: int, body: string}> $responses
     */
    public function __construct(
        private array $responses = []
    ) {}

    /**
     * @param list<string> $headerLines
     *
     * @return array{status: int, body: string}
     */
    public function send(string $method, string $url, ?string $body, array $headerLines): array
    {
        $this->requests[] = [
            'method' => $method,
            'url' => $url,
            'body' => $body,
            'headers' => $headerLines,
        ];

        if ($this->responses !== []) {
            return array_shift($this->responses);
        }

        return ['status' => 500, 'body' => '{"message":"FakeHttpTransport: empty response queue"}'];
    }
}
