<?php

declare(strict_types=1);

namespace Amashukov\Etherscan;

use SensitiveParameter;
use Amashukov\Etherscan\Vo\InternalTransaction;
use Amashukov\Etherscan\Vo\TokenTransfer;
use Amashukov\Etherscan\Vo\Transaction;
use InvalidArgumentException;
use JsonException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final readonly class EtherscanClient implements EtherscanClientInterface
{
    public const string DEFAULT_BASE_URL = 'https://api.etherscan.io/v2/api';

    private const string NO_RESULTS = 'No transactions found';

    public function __construct(
        private ClientInterface $http,
        private RequestFactoryInterface $requestFactory,
        #[SensitiveParameter]
        private string $apiKey,
        private int $chainId,
        private string $baseUrl = self::DEFAULT_BASE_URL,
        private LoggerInterface $logger = new NullLogger(),
    ) {}

    public function transactions(string $address, AddressHistoryQuery $query = new AddressHistoryQuery()): array
    {
        return array_map(Transaction::fromRow(...), $this->rows('txlist', ['address' => $address], $query));
    }

    public function tokenTransfers(?string $address, ?string $contractAddress = null, AddressHistoryQuery $query = new AddressHistoryQuery()): array
    {
        $filter = array_filter(['address' => $address, 'contractaddress' => $contractAddress], static fn (?string $value): bool => null !== $value && '' !== $value);
        if ([] === $filter) {
            throw new InvalidArgumentException('Provide at least one of address or contractaddress.');
        }

        return array_map(TokenTransfer::fromRow(...), $this->rows('tokentx', $filter, $query));
    }

    public function internalTransactions(string $address, AddressHistoryQuery $query = new AddressHistoryQuery()): array
    {
        return array_map(InternalTransaction::fromRow(...), $this->rows('txlistinternal', ['address' => $address], $query));
    }

    /**
     * @param array<string, string> $filter
     *
     * @return list<array<array-key, mixed>>
     */
    private function rows(string $action, array $filter, AddressHistoryQuery $query): array
    {
        if ('' === $this->apiKey) {
            throw new EtherscanConfigurationException('Etherscan API key is not configured.');
        }

        $context = ['action' => $action, 'chain_id' => $this->chainId] + $filter + $query->parameters();
        $url     = $this->baseUrl . '?' . http_build_query(['chainid' => (string) $this->chainId, 'module' => 'account', 'action' => $action] + $filter + $query->parameters() + ['apikey' => $this->apiKey]);
        $this->logger->debug('Etherscan request', $context);

        try {
            $response = $this->http->sendRequest($this->requestFactory->createRequest('GET', $url));
        } catch (ClientExceptionInterface $exception) {
            $failure = new EtherscanException(sprintf('Etherscan %s could not be reached: %s', $action, $exception::class), 0, $exception);
            $this->logger->error('Etherscan request failed', $context + ['exception' => $failure]);

            throw $failure;
        }

        $body = (string) $response->getBody();
        try {
            $envelope = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            $failure = new EtherscanException(sprintf('Etherscan %s answered HTTP %d with a body that is not JSON.', $action, $response->getStatusCode()), $response->getStatusCode(), $exception);
            $this->logger->error('Etherscan answer is not JSON', $context + ['exception' => $failure]);

            throw $failure;
        }
        if (!is_array($envelope)) {
            throw new EtherscanException(sprintf('Etherscan %s answered a JSON value that is not an object.', $action));
        }

        return $this->result($action, $envelope, $context);
    }

    /**
     * @param array<array-key, mixed> $envelope
     * @param array<string, mixed>    $context
     *
     * @return list<array<array-key, mixed>>
     */
    private function result(string $action, array $envelope, array $context): array
    {
        $status  = $envelope['status'] ?? null;
        $message = is_string($envelope['message'] ?? null) ? $envelope['message'] : '';
        $result  = $envelope['result'] ?? null;

        if ('1' === $status && is_array($result)) {
            $rows = [];
            foreach ($result as $row) {
                if (!is_array($row)) {
                    throw new EtherscanException(sprintf('Etherscan %s returned a row that is not an object.', $action));
                }
                $rows[] = $row;
            }
            $this->logger->debug('Etherscan answer', $context + ['rows' => count($rows)]);

            return $rows;
        }

        if ('0' === $status && self::NO_RESULTS === $message && (is_array($result) || null === $result)) {
            return [];
        }

        $reason = is_string($result) ? $result : $message;
        $this->logger->warning('Etherscan refused the request', $context + ['reason' => $reason]);
        if (1 === preg_match('/rate limit/i', $reason)) {
            throw new EtherscanRateLimitException(sprintf('Etherscan %s: %s', $action, $reason));
        }
        if (1 === preg_match('/api.?key/i', $reason)) {
            throw new EtherscanConfigurationException(sprintf('Etherscan %s: %s', $action, $reason));
        }

        throw new EtherscanException(sprintf('Etherscan %s: %s', $action, '' === $reason ? 'unexpected answer' : $reason));
    }
}
