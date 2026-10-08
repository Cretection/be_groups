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

namespace Cretection\BeGroups\Tests\Functional\Fixtures\Extensions\be_groups_test_rewrite\Classes;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;

/**
 * Simulates another extension that changes data while groups are saved. What it changes is
 * chosen by the description of the saved group ("rewrite:…"), or by the title of new groups:
 *
 * - new page tree mount "Editors": page 2 is added to the page mounts,
 * - new access rights "Database error": a query fails,
 * - new access rights "Unknown column": a column that does not exist is written,
 * - "rewrite:page-mount": page 2 is added to the page mounts when the kind changes,
 * - "rewrite:add-subgroup": group 12 is added to the subgroups of the new role,
 * - "rewrite:widen-other-group": group 12 gets another module,
 * - "rewrite:delete-other-group": group 12 is removed from the database,
 * - "rewrite:add-group-to-user": user 2 gets group 12,
 * - "rewrite:create-user": user 100 is created with group 12,
 * - "rewrite:show-group": the group is no longer hidden,
 * - "rewrite:move-first-subgroup-last": the first subgroup of the new role becomes the last one.
 */
final readonly class RewritePermissions
{
    public function __construct(
        private ConnectionPool $connectionPool,
    ) {}

    /**
     * @param array<string, mixed> $fieldArray
     */
    public function processDatamap_postProcessFieldArray(string $status, string $table, int|string $id, array &$fieldArray, DataHandler $dataHandler): void
    {
        if ($table !== 'be_groups') {
            return;
        }
        if ($status === 'new') {
            $kind = is_string($fieldArray['tx_begroups_kind'] ?? null) ? $fieldArray['tx_begroups_kind'] : '';
            $title = is_string($fieldArray['title'] ?? null) ? $fieldArray['title'] : '';
            match ($kind . ':' . $title) {
                'db_mount:Editors' => $fieldArray['db_mountpoints'] = '1,2',
                'acl:Database error' => $this->connectionPool->getConnectionForTable('be_groups')->executeQuery('SELECT * FROM be_groups_table_that_does_not_exist'),
                'acl:Unknown column' => $fieldArray['column_that_does_not_exist'] = 1,
                default => null,
            };
            return;
        }
        if (!isset($fieldArray['tx_begroups_kind'])) {
            return;
        }
        $connection = $this->connectionPool->getConnectionForTable('be_groups');
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('be_groups');
        // Hidden groups as well
        $queryBuilder->getRestrictions()->removeAll();
        $description = $queryBuilder->select('description')->from('be_groups')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter((int)$id, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();
        match ($description) {
            'rewrite:page-mount' => $fieldArray['db_mountpoints'] = '1,2',
            'rewrite:add-subgroup' => $fieldArray['subgroup'] = (is_string($fieldArray['subgroup'] ?? null) ? $fieldArray['subgroup'] : '') . ',12',
            'rewrite:widen-other-group' => $connection->update('be_groups', ['groupMods' => 'web_list,web_info'], ['uid' => 12]),
            'rewrite:delete-other-group' => $connection->delete('be_groups', ['uid' => 12]),
            'rewrite:add-group-to-user' => $this->connectionPool->getConnectionForTable('be_users')->update('be_users', ['usergroup' => '2,12'], ['uid' => 2]),
            'rewrite:create-user' => $this->connectionPool->getConnectionForTable('be_users')->insert('be_users', ['uid' => 100, 'pid' => 0, 'username' => 'created', 'usergroup' => '12']),
            'rewrite:show-group' => $fieldArray['hidden'] = 0,
            'rewrite:move-first-subgroup-last' => $fieldArray['subgroup'] = $this->moveFirstEntryLast($fieldArray['subgroup'] ?? ''),
            default => null,
        };
    }

    private function moveFirstEntryLast(mixed $list): string
    {
        $entries = explode(',', is_string($list) ? $list : '');
        $entries[] = array_shift($entries);
        return implode(',', $entries);
    }
}
