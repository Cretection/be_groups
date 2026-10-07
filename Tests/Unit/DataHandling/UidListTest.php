<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "be_groups".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 */

namespace Cretection\BeGroups\Tests\Unit\DataHandling;

use Cretection\BeGroups\DataHandling\UidList;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(UidList::class)]
final class UidListTest extends UnitTestCase
{
    /**
     * @return array<string, array{mixed, list<int>}>
     */
    public static function valueDataProvider(): array
    {
        return [
            'comma-separated string' => ['3,7,12', [3, 7, 12]],
            'string with spaces' => [' 3 , 7 ', [3, 7]],
            'array of strings' => [['3', '7'], [3, 7]],
            'array of integers' => [[3, 7], [3, 7]],
            'single integer' => [5, [5]],
            'table-prefixed values' => ['be_groups_3,be_groups_7', [3, 7]],
            'duplicates are removed, order is kept' => ['7,3,7', [7, 3]],
            'zero, negative and non-numeric values are dropped' => ['0,-1,abc,1e3,2', [2]],
            'empty string' => ['', []],
            'null' => [null, []],
            'nested arrays are ignored' => [[['3'], '4'], [4]],
        ];
    }

    /**
     * @param list<int> $expected
     */
    #[Test]
    #[DataProvider('valueDataProvider')]
    public function fromValueNormalizesRelationValues(mixed $value, array $expected): void
    {
        self::assertSame($expected, UidList::fromValue($value)->uids);
    }

    #[Test]
    public function withoutUidsOfReturnsUidsMissingInTheOtherList(): void
    {
        $incoming = UidList::fromValue('1,2,3,4');
        $stored = UidList::fromValue('2,4');

        self::assertSame([1, 3], $incoming->withoutUidsOf($stored));
    }

    #[Test]
    public function withoutKeepsTheOrderOfTheRemainingUids(): void
    {
        self::assertSame('4,1', UidList::fromValue('4,3,1')->without([3])->toString());
    }
}
