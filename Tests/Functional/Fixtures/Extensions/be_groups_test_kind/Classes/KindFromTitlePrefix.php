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

namespace Cretection\BeGroups\Tests\Functional\Fixtures\Extensions\be_groups_test_kind\Classes;

use Cretection\BeGroups\Event\ModifyKindOfNewGroupEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;

/**
 * Chooses the kind of new groups from the prefix of their title, as a synchronisation tool could.
 */
final readonly class KindFromTitlePrefix
{
    private const PREFIXES = ['R_' => 'role', 'DBM_' => 'db_mount', 'X_' => 'kind_that_does_not_exist'];

    #[AsEventListener('cretection/be-groups-test-kind/kind-from-title-prefix')]
    public function __invoke(ModifyKindOfNewGroupEvent $event): void
    {
        $title = $event->getRecord()['title'] ?? '';
        foreach (self::PREFIXES as $prefix => $kind) {
            if (is_string($title) && str_starts_with($title, $prefix)) {
                $event->setKind($kind);
            }
        }
    }
}
