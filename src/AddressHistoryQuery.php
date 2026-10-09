<?php

declare(strict_types=1);

namespace Amashukov\Etherscan;

use InvalidArgumentException;

final readonly class AddressHistoryQuery
{
    public const int MAX_PAGE_SIZE = 10_000;

    public function __construct(
        public int $startBlock = 0,
        public ?int $endBlock = null,
        public int $page = 1,
        public int $pageSize = 100,
        public Sort $sort = Sort::Ascending,
    ) {
        if ($startBlock < 0) {
            throw new InvalidArgumentException(sprintf('The start block must not be negative, got %d.', $startBlock));
        }
        if (null !== $endBlock && $endBlock < $startBlock) {
            throw new InvalidArgumentException(sprintf('The end block %d is before the start block %d.', $endBlock, $startBlock));
        }
        if ($page < 1) {
            throw new InvalidArgumentException(sprintf('Pages start at 1, got %d.', $page));
        }
        if ($pageSize < 1 || $pageSize > self::MAX_PAGE_SIZE) {
            throw new InvalidArgumentException(sprintf('A page holds 1 to %d rows, got %d.', self::MAX_PAGE_SIZE, $pageSize));
        }
    }

    /**
     * @return array<string, string>
     */
    public function parameters(): array
    {
        return [
            'startblock' => (string) $this->startBlock,
            'endblock'   => (string) ($this->endBlock ?? 99_999_999),
            'page'       => (string) $this->page,
            'offset'     => (string) $this->pageSize,
            'sort'       => $this->sort->value,
        ];
    }
}
