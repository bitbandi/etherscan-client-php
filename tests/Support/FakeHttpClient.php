<?php

declare(strict_types=1);

namespace Amashukov\Etherscan\Tests\Support;

use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

final class FakeHttpClient implements ClientInterface
{
    /**
     * @var list<RequestInterface>
     */
    private array $sent = [];

    public function __construct(
        private readonly int $status = 200,
        private readonly string $body = '{}',
        private readonly bool $unreachable = false,
    ) {}

    /**
     * @return list<RequestInterface>
     */
    public function requests(): array
    {
        return $this->sent;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->sent[] = $request;
        if ($this->unreachable) {
            throw new class ('connection refused') extends RuntimeException implements ClientExceptionInterface {};
        }

        return new Response($this->status, ['Content-Type' => 'application/json'], $this->body);
    }
}
