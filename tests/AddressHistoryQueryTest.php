<?php

declare(strict_types=1);

namespace Amashukov\Etherscan\Tests;

use Amashukov\Etherscan\AddressHistoryQuery;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(AddressHistoryQuery::class)]
final class AddressHistoryQueryTest extends TestCase
{
    public function testTheDefaultsReadTheWholeHistoryOldestFirst(): void
    {
        self::assertSame(['startblock' => '0', 'endblock' => '99999999', 'page' => '1', 'offset' => '100', 'sort' => 'asc'], (new AddressHistoryQuery())->parameters());
    }

    /**
     * @return iterable<string, array{int, ?int, int, int}>
     */
    public static function invalid(): iterable
    {
        yield 'negative start' => [-1, null, 1, 100];
        yield 'end before start' => [10, 9, 1, 100];
        yield 'page zero' => [0, null, 0, 100];
        yield 'empty page' => [0, null, 1, 0];
        yield 'page too large' => [0, null, 1, 10_001];
    }

    #[DataProvider('invalid')]
    public function testAnImpossibleRangeIsRefused(int $start, ?int $end, int $page, int $size): void
    {
        $this->expectException(InvalidArgumentException::class);

        new AddressHistoryQuery($start, $end, $page, $size);
    }
}
