<?php

declare(strict_types=1);

namespace Amashukov\Etherscan\Vo;

final readonly class Transaction
{
    /**
     * @param numeric-string $value
     */
    public function __construct(
        public string $hash,
        public int $blockNumber,
        public int $timestamp,
        public int $transactionIndex,
        public string $from,
        public ?string $to,
        public string $value,
        public bool $failed,
        public string $input,
    ) {}

    /**
     * @param array<array-key, mixed> $values
     */
    public static function fromRow(array $values): self
    {
        $row = new Row($values, 'transaction');

        return new self(
            hash: strtolower($row->string('hash')),
            blockNumber: $row->integer('blockNumber'),
            timestamp: $row->integer('timeStamp'),
            transactionIndex: $row->integer('transactionIndex'),
            from: $row->address('from'),
            to: $row->optionalAddress('to'),
            value: $row->amount('value'),
            failed: $row->flag('isError') || '0' === $row->optionalString('txreceipt_status'),
            input: $row->optionalString('input') ?? '0x',
        );
    }
}
