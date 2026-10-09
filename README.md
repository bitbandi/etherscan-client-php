# amashukov/etherscan-client-php

Typed PHP client for the [Etherscan API V2](https://docs.etherscan.io/) — address history of any EVM chain over a PSR-18 transport, with typed Value Objects and typed failures.

[![CI](https://img.shields.io/github/actions/workflow/status/AndreyMashukov/etherscan-client-php/ci.yml?branch=main&label=CI)](https://github.com/AndreyMashukov/etherscan-client-php/actions)
[![PHPStan L9](https://img.shields.io/github/actions/workflow/status/AndreyMashukov/etherscan-client-php/stan.yml?branch=main&label=PHPStan%20L9)](https://github.com/AndreyMashukov/etherscan-client-php/actions)
[![License](https://img.shields.io/github/license/AndreyMashukov/etherscan-client-php)](LICENSE)

An archive-grade view of an address is what a pruned or proxied JSON-RPC node cannot give you: such nodes serve recent blocks only, so a service that missed blocks has no way to read them back through the node. Etherscan indexes the whole chain. This client asks it for the transactions that touched an address — normal, ERC-20 and internal — in a block range, one page at a time, and returns them typed. It deliberately does no more than that: no retries, no scheduling, no persistence. Use the transaction hashes it returns to read receipts from your own node when the exact event matters (Etherscan's ERC-20 list does not carry the log index).

## Features

- **Etherscan API V2** — one key for every supported chain; the chain is an explicit constructor argument (`chainid`).
- **Three address histories** — `transactions()` (`txlist`), `tokenTransfers()` (`tokentx`, filtered by token contract) and `internalTransactions()` (`txlistinternal`).
- **Typed Value Objects** — `Transaction`, `TokenTransfer`, `InternalTransaction`; addresses and hashes lower-cased, amounts as decimal strings in the smallest unit (wei / token units), safe past `PHP_INT_MAX`.
- **Typed failures** — `EtherscanRateLimitException` (over the key's rate limit), `EtherscanConfigurationException` (missing or refused key), `EtherscanException` (any other API error, an unreachable API, a body that is not JSON, a malformed row). "No transactions found" is an empty list, not an error.
- **Bounded queries** — `AddressHistoryQuery` validates the block range, page and page size (1–10 000) before a request is made.
- **The API key never reaches the log** — PSR-3 debug lines carry the action, chain, address and range only.
- **Transport-agnostic** — bring any PSR-18 client and PSR-17 request factory; rate limiting and retries belong to your pipeline.

## Installation

```bash
composer require amashukov/etherscan-client-php
```

## Usage

```php
use Amashukov\Etherscan\AddressHistoryQuery;
use Amashukov\Etherscan\EtherscanClient;
use Amashukov\Etherscan\EtherscanRateLimitException;
use Amashukov\Etherscan\Sort;
use Nyholm\Psr7\Factory\Psr17Factory;

$client = new EtherscanClient(
    http: $psr18Client,
    requestFactory: new Psr17Factory(),
    apiKey: $apiKey,
    chainId: 1,
);

$query = new AddressHistoryQuery(startBlock: 21_000_000, endBlock: 21_010_000, pageSize: 100, sort: Sort::Ascending);

foreach ($client->tokenTransfers('0x…address', '0x…token', $query) as $transfer) {
    // $transfer->hash, $transfer->blockNumber, $transfer->from, $transfer->to, $transfer->value (token units)
}

try {
    $client->transactions('0x…address', $query);
} catch (EtherscanRateLimitException) {
    // back off — the free tier allows a few calls per second
}
```

Pagination: a page holds at most `pageSize` rows; ask for `page: 2` and so on until a page comes back shorter than `pageSize`.

## Development

```bash
docker compose up -d
docker exec etherscan-client-php-app-1 composer install
docker exec etherscan-client-php-app-1 sh -c 'vendor/bin/phpunit && vendor/bin/phpstan analyse && vendor/bin/php-cs-fixer fix --dry-run && vendor/bin/rector process --dry-run'
```

## License

MIT
