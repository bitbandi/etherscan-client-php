<?php

declare(strict_types=1);

namespace Amashukov\Etherscan\Tests;

use Amashukov\Etherscan\AddressHistoryQuery;
use Amashukov\Etherscan\EtherscanClient;
use Amashukov\Etherscan\EtherscanConfigurationException;
use Amashukov\Etherscan\EtherscanException;
use Amashukov\Etherscan\EtherscanRateLimitException;
use Amashukov\Etherscan\Sort;
use Amashukov\Etherscan\Tests\Support\FakeHttpClient;
use Nyholm\Psr7\Factory\Psr17Factory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Stringable;

#[CoversClass(EtherscanClient::class)]
final class EtherscanClientTest extends TestCase
{
    private const string KEY = 'TESTKEY0000000000000000000000000';

    private const string ADDRESS = '0x00000000000000000000000000000000000000aa';

    private const string TOKEN = '0x00000000000000000000000000000000000000bb';

    public function testNormalTransactionsAreTypedAndTheRequestNamesChainRangeAndKey(): void
    {
        $http   = new FakeHttpClient(body: json_encode(['status' => '1', 'message' => 'OK', 'result' => [[
            'blockNumber' => '21000000', 'timeStamp' => '1760000000', 'hash' => '0x' . str_repeat('0', 63) . 'A', 'transactionIndex' => '7',
            'from' => '0x00000000000000000000000000000000000000CC', 'to' => self::ADDRESS, 'value' => '1500000000000000000',
            'isError' => '0', 'txreceipt_status' => '1', 'input' => '0x',
        ]]], JSON_THROW_ON_ERROR));
        $client = $this->client($http);

        $transactions = $client->transactions(self::ADDRESS, new AddressHistoryQuery(startBlock: 20_999_000, endBlock: 21_000_100, sort: Sort::Descending));

        self::assertCount(1, $transactions);
        self::assertSame('0x' . str_repeat('0', 63) . 'a', $transactions[0]->hash);
        self::assertSame(21_000_000, $transactions[0]->blockNumber);
        self::assertSame('0x00000000000000000000000000000000000000cc', $transactions[0]->from);
        self::assertSame('1500000000000000000', $transactions[0]->value);
        self::assertFalse($transactions[0]->failed);
        parse_str($http->requests()[0]->getUri()->getQuery(), $query);
        self::assertSame(['chainid' => '1', 'module' => 'account', 'action' => 'txlist', 'address' => self::ADDRESS, 'startblock' => '20999000', 'endblock' => '21000100', 'page' => '1', 'offset' => '100', 'sort' => 'desc', 'apikey' => self::KEY], $query);
        self::assertSame('api.etherscan.io', $http->requests()[0]->getUri()->getHost());
    }

    public function testTokenTransfersFilterByTheContract(): void
    {
        $http      = new FakeHttpClient(body: json_encode(['status' => '1', 'message' => 'OK', 'result' => [[
            'blockNumber' => '5', 'timeStamp' => '6', 'hash' => '0xbeef', 'transactionIndex' => '0', 'contractAddress' => self::TOKEN,
            'from' => '0x01', 'to' => self::ADDRESS, 'value' => '20000000', 'tokenDecimal' => '6', 'tokenSymbol' => 'TKN',
        ]]], JSON_THROW_ON_ERROR));
        $transfers = $this->client($http)->tokenTransfers(self::ADDRESS, self::TOKEN);

        self::assertSame('20000000', $transfers[0]->value);
        self::assertSame(6, $transfers[0]->tokenDecimal);
        self::assertSame(self::TOKEN, $transfers[0]->contractAddress);
        parse_str($http->requests()[0]->getUri()->getQuery(), $query);
        self::assertSame(['tokentx', self::TOKEN], [$query['action'] ?? null, $query['contractaddress'] ?? null]);
    }

    public function testInternalTransactionsCarryTheirTraceAndFailure(): void
    {
        $http     = new FakeHttpClient(body: json_encode(['status' => '1', 'message' => 'OK', 'result' => [[
            'blockNumber' => '9', 'timeStamp' => '10', 'hash' => '0xabc', 'from' => '0x02', 'to' => self::ADDRESS, 'value' => '7',
            'traceId' => '0_1', 'type' => 'call', 'isError' => '1',
        ]]], JSON_THROW_ON_ERROR));
        $internal = $this->client($http)->internalTransactions(self::ADDRESS);

        self::assertSame('0_1', $internal[0]->traceId);
        self::assertTrue($internal[0]->failed);
    }

    public function testAnAddressWithoutTransactionsAnswersAnEmptyList(): void
    {
        self::assertSame([], $this->client(new FakeHttpClient(body: '{"status":"0","message":"No transactions found","result":[]}'))->transactions(self::ADDRESS));
    }

    /**
     * @return iterable<string, array{string, class-string<EtherscanException>}>
     */
    public static function refusals(): iterable
    {
        yield 'rate limit' => ['{"status":"0","message":"NOTOK","result":"Max calls per sec rate limit reached (3/sec)"}', EtherscanRateLimitException::class];
        yield 'invalid key' => ['{"status":"0","message":"NOTOK","result":"Invalid API Key (#err2)|1"}', EtherscanConfigurationException::class];
        yield 'other error' => ['{"status":"0","message":"NOTOK","result":"Error! Invalid address format"}', EtherscanException::class];
        yield 'not json' => ['<html>502 Bad Gateway</html>', EtherscanException::class];
        yield 'malformed row' => ['{"status":"1","message":"OK","result":[{"blockNumber":"1","timeStamp":"2","hash":"0x1","transactionIndex":"0","from":"0x1","to":"0x2","value":"1.5e18"}]}', EtherscanException::class];
        yield 'row is not an object' => ['{"status":"1","message":"OK","result":["0x1"]}', EtherscanException::class];
    }

    /**
     * @param class-string<EtherscanException> $exception
     */
    #[DataProvider('refusals')]
    public function testEveryRefusalIsATypedFailure(string $body, string $exception): void
    {
        $this->expectException($exception);

        $this->client(new FakeHttpClient(body: $body))->transactions(self::ADDRESS);
    }

    public function testAMissingKeyFailsBeforeAnyRequest(): void
    {
        $http = new FakeHttpClient();

        try {
            (new EtherscanClient($http, new Psr17Factory(), '', 1))->transactions(self::ADDRESS);
            self::fail('a missing key must fail');
        } catch (EtherscanConfigurationException) {
            self::assertSame([], $http->requests());
        }
    }

    public function testAnUnreachableApiIsAnEtherscanFailure(): void
    {
        $this->expectException(EtherscanException::class);

        $this->client(new FakeHttpClient(unreachable: true))->transactions(self::ADDRESS);
    }

    public function testTheKeyNeverReachesTheLog(): void
    {
        $logger = new class extends AbstractLogger {
            public string $written = '';

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->written .= $message . json_encode($context);
            }
        };

        try {
            (new EtherscanClient(new FakeHttpClient(body: '{"status":"0","message":"NOTOK","result":"Max rate limit reached"}'), new Psr17Factory(), self::KEY, 1, logger: $logger))->transactions(self::ADDRESS);
        } catch (EtherscanRateLimitException) {
        }

        self::assertStringContainsString('txlist', $logger->written);
        self::assertStringNotContainsString(self::KEY, $logger->written);
    }

    private function client(FakeHttpClient $http): EtherscanClient
    {
        return new EtherscanClient($http, new Psr17Factory(), self::KEY, 1);
    }
}
