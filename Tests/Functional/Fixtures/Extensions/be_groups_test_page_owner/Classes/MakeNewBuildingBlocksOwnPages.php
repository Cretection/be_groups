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

namespace Cretection\BeGroups\Tests\Functional\Fixtures\Extensions\be_groups_test_page_owner\Classes;

use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;

/**
 * Simulates another extension that changes a table other than be_groups and be_users while
 * groups are saved: every new building block becomes the owner group of page 1.
 */
final readonly class MakeNewBuildingBlocksOwnPages
{
    public function __construct(
        private ConnectionPool $connectionPool,
    ) {}

    /**
     * @param array<string, mixed> $fieldArray
     */
    public function processDatamap_afterDatabaseOperations(string $status, string $table, int|string $id, array $fieldArray, DataHandler $dataHandler): void
    {
        $uid = $dataHandler->substNEWwithIDs[$id] ?? null;
        if ($status !== 'new' || $table !== 'be_groups' || !is_int($uid) || in_array($fieldArray['tx_begroups_kind'] ?? null, ['role', 'classic'], true)) {
            return;
        }
        $this->connectionPool->getConnectionForTable('pages')->update('pages', ['perms_groupid' => $uid], ['uid' => 1]);
    }
}
