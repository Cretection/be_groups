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

namespace Cretection\BeGroups\EventListener;

use Cretection\BeGroups\Domain\Kind\GroupKindLookup;
use Cretection\BeGroups\Domain\Kind\KindPrefix;
use TYPO3\CMS\Beuser\Event\AfterBackendGroupFilterListIsAssembledEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Extbase\DomainObject\DomainObjectInterface;

/**
 * Shows the groups in the group filter of the core module "Users" with the prefix of their kind.
 *
 * The filter only needs uid and title, so the groups are passed on as plain arrays: the
 * domain objects of the module are never changed.
 *
 * @internal
 */
final readonly class PrefixGroupsInUserFilter
{
    public function __construct(
        private GroupKindLookup $groupKindLookup,
        private KindPrefix $kindPrefix,
    ) {}

    #[AsEventListener('cretection/be-groups/prefix-groups-in-user-filter')]
    public function __invoke(AfterBackendGroupFilterListIsAssembledEvent $event): void
    {
        $event->backendGroups = array_map(function (mixed $group): mixed {
            $uid = $group instanceof DomainObjectInterface ? $group->getUid() : null;
            if ($uid === null) {
                return $group;
            }
            return [
                'uid' => $uid,
                'title' => $this->kindPrefix->prefixTitle($this->groupKindLookup->getKind($uid), $this->groupKindLookup->getTitle($uid)),
            ];
        }, $event->backendGroups);
    }
}
