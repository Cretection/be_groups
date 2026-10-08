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

use Cretection\BeGroups\DataHandling\RelationList;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(RelationList::class)]
final class RelationListTest extends UnitTestCase
{
    /**
     * @return array<string, array{mixed, list<int|string>}>
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
            'uid|label pairs' => ['3|Editors,7|Mounts', [3, 7]],
            'url-encoded values' => ['%33,be_groups_%37', [3, 7]],
            'NEW placeholders' => ['NEW1,3,NEW64a1b2c3.5', ['NEW1', 3, 'NEW64a1b2c3.5']],
            'duplicates are removed, order is kept' => ['7,3,7,3|x', [7, 3]],
            'zero, negative and garbage are dropped' => ['0,-1,abc,1e3,NEW,2', [2]],
            'empty string' => ['', []],
            'null' => [null, []],
            'nested arrays are ignored' => [[['3'], '4'], [4]],
        ];
    }

    /**
     * @param list<int|string> $expected
     */
    #[Test]
    #[DataProvider('valueDataProvider')]
    public function fromValueUnderstandsEveryNotationOfTheDataHandler(mixed $value, array $expected): void
    {
        self::assertSame($expected, RelationList::fromValue($value)->entries);
    }

    #[Test]
    public function fromStoredValueReadsTheListLikeTypo3(): void
    {
        self::assertSame([7, 8, 9], RelationList::fromStoredValue('be_groups_7,%37,NEW1,07,7abc,+8, 9,0,-3,9')->entries);
    }

    #[Test]
    public function getUidsSkipsPlaceholders(): void
    {
        self::assertSame([3, 5], RelationList::fromValue('3,NEW1,5')->getUids());
    }

    #[Test]
    public function getEntriesMissingInReturnsAddedEntriesInOrder(): void
    {
        $incoming = RelationList::fromValue('NEW1,1,2,3,4');
        $stored = RelationList::fromValue('2,4');

        self::assertSame(['NEW1', 1, 3], $incoming->getEntriesMissingIn($stored));
    }

    #[Test]
    public function withoutKeepsTheOrderOfTheRemainingEntries(): void
    {
        self::assertSame('4,NEW2,1', RelationList::fromValue('4,3,NEW2,1')->without([3])->toString());
    }

    #[Test]
    public function withEntryAfterInsertsDirectlyAfterTheReference(): void
    {
        self::assertSame('1,2,9,3', RelationList::fromValue('1,2,3')->withEntryAfter(9, 2)->toString());
        self::assertSame('1,2,9', RelationList::fromValue('1,2')->withEntryAfter(9, 5)->toString());
        self::assertSame('1,9', RelationList::fromValue('1,9')->withEntryAfter(9, 1)->toString());
    }
}
