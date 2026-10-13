<?php

declare(strict_types=1);

namespace Amashukov\Etherscan;

use Amashukov\Etherscan\Vo\InternalTransaction;
use Amashukov\Etherscan\Vo\TokenTransfer;
use Amashukov\Etherscan\Vo\Transaction;

interface EtherscanClientInterface
{
    /**
     * Normal (external) transactions that touch the address — sent or received — in the query's block range, one page of them (`module=account&action=txlist`). An address without transactions answers an empty list.
     *
     * @return list<Transaction>
     *
     * @throws EtherscanRateLimitException when the API key is over its rate limit
     * @throws EtherscanConfigurationException when the API key is missing or refused
     * @throws EtherscanException on any other API error or a malformed answer
     */
    public function transactions(string $address, AddressHistoryQuery $query = new AddressHistoryQuery()): array;

    /**
     * ERC-20 transfers in the query's block range (`action=tokentx`), filtered by the address (to or from), the token contract, or both. Provide at least one of address or contractAddress; null or an empty string counts as not given. Etherscan does not return the log index of a transfer: use the hash to read the receipt from a node when the exact event matters.
     *
     * @return list<TokenTransfer>
     *
     * @throws EtherscanRateLimitException when the API key is over its rate limit
     * @throws EtherscanConfigurationException when the API key is missing or refused
     * @throws EtherscanException on any other API error or a malformed answer
     * @throws \InvalidArgumentException when neither address nor contractAddress is given
     */
    public function tokenTransfers(?string $address, ?string $contractAddress = null, AddressHistoryQuery $query = new AddressHistoryQuery()): array;

    /**
     * Internal transactions (value moved by contract execution) that touch the address in the query's block range (`action=txlistinternal`). These leave no receipt log; their amount and recipient come from Etherscan's traces.
     *
     * @return list<InternalTransaction>
     *
     * @throws EtherscanRateLimitException when the API key is over its rate limit
     * @throws EtherscanConfigurationException when the API key is missing or refused
     * @throws EtherscanException on any other API error or a malformed answer
     */
    public function internalTransactions(string $address, AddressHistoryQuery $query = new AddressHistoryQuery()): array;
}
