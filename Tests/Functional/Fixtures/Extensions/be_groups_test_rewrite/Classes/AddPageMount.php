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

use TYPO3\CMS\Core\DataHandling\DataHandler;

/**
 * Simulates another extension that changes permissions while a group is saved: new groups
 * with page mounts and groups whose kind changes get page 2 as additional page mount.
 */
final class AddPageMount
{
    /**
     * @param array<string, mixed> $fieldArray
     */
    public function processDatamap_postProcessFieldArray(string $status, string $table, int|string $id, array &$fieldArray, DataHandler $dataHandler): void
    {
        if ($table !== 'be_groups') {
            return;
        }
        if ($status === 'new' && is_string($fieldArray['db_mountpoints'] ?? null) && $fieldArray['db_mountpoints'] !== '') {
            $fieldArray['db_mountpoints'] .= ',2';
        }
        if ($status === 'update' && isset($fieldArray['tx_begroups_kind'])) {
            $fieldArray['db_mountpoints'] = '1,2';
        }
    }
}
