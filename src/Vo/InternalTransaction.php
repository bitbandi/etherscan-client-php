<?php

declare(strict_types=1);

namespace Amashukov\Etherscan\Vo;

final readonly class InternalTransaction
{
    /**
     * @param numeric-string $value
     */
    public function __construct(
        public string $hash,
        public int $blockNumber,
        public int $timestamp,
        public string $from,
        public ?string $to,
        public string $value,
        public ?string $traceId,
        public ?string $type,
        public bool $failed,
    ) {}

    /**
     * @param array<array-key, mixed> $values
     */
    public static function fromRow(array $values): self
    {
        $row = new Row($values, 'internal transaction');

        return new self(
            hash: strtolower($row->string('hash')),
            blockNumber: $row->integer('blockNumber'),
            timestamp: $row->integer('timeStamp'),
            from: $row->address('from'),
            to: $row->optionalAddress('to'),
            value: $row->amount('value'),
            traceId: $row->optionalString('traceId'),
            type: $row->optionalString('type'),
            failed: $row->flag('isError'),
        );
    }
}
