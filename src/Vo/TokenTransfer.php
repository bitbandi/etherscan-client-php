<?php

declare(strict_types=1);

namespace Amashukov\Etherscan\Vo;

final readonly class TokenTransfer
{
    /**
     * @param numeric-string $value
     */
    public function __construct(
        public string $hash,
        public int $blockNumber,
        public int $timestamp,
        public int $transactionIndex,
        public string $contractAddress,
        public string $from,
        public string $to,
        public string $value,
        public int $tokenDecimal,
        public ?string $tokenSymbol,
    ) {}

    /**
     * @param array<array-key, mixed> $values
     */
    public static function fromRow(array $values): self
    {
        $row = new Row($values, 'token transfer');

        return new self(
            hash: strtolower($row->string('hash')),
            blockNumber: $row->integer('blockNumber'),
            timestamp: $row->integer('timeStamp'),
            transactionIndex: $row->integer('transactionIndex'),
            contractAddress: $row->address('contractAddress'),
            from: $row->address('from'),
            to: $row->address('to'),
            value: $row->amount('value'),
            tokenDecimal: $row->integer('tokenDecimal'),
            tokenSymbol: $row->optionalString('tokenSymbol'),
        );
    }
}
