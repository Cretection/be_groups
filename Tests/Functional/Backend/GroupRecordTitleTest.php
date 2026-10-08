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

namespace Cretection\BeGroups\Tests\Functional\Backend;

use Cretection\BeGroups\Backend\GroupRecordTitle;
use Cretection\BeGroups\Domain\Kind\KindPrefix;
use Cretection\BeGroups\Domain\Kind\KindRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

#[CoversClass(GroupRecordTitle::class)]
#[CoversClass(KindPrefix::class)]
#[CoversClass(KindRegistry::class)]
final class GroupRecordTitleTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['cretection/be-groups'];

    /**
     * @return array<string, array{mixed, string}>
     */
    public static function kindDataProvider(): array
    {
        return [
            'role' => ['role', 'META: Editors'],
            'access rights' => ['acl', 'ACL: Editors'],
            'page group' => ['page_group', 'PG: Editors'],
            'page tree mount' => ['db_mount', 'DBM: Editors'],
            'file mount' => ['file_mount', 'FM: Editors'],
            'file operations' => ['file_operations', 'FO: Editors'],
            'category mount' => ['category_mount', 'CM: Editors'],
            'language' => ['language', 'L: Editors'],
            'TSconfig' => ['tsconfig', 'TS: Editors'],
            'classic' => ['classic', 'Editors'],
            'kind that is not configured' => ['3', 'Editors'],
            'value of the record form' => [['role'], 'META: Editors'],
        ];
    }

    #[Test]
    #[DataProvider('kindDataProvider')]
    public function showsTheKindAsPrefixOfTheTitle(mixed $kind, string $expectedTitle): void
    {
        self::assertSame($expectedTitle, BackendUtility::getRecordTitle('be_groups', ['uid' => 1, 'title' => 'Editors', 'tx_begroups_kind' => $kind]));
    }
}
