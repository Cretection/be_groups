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

use Cretection\BeGroups\Domain\Kind\GroupKind;
use Cretection\BeGroups\Form\ItemsProcessor\UnknownKindItemGroup;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Configuration\Event\AfterTcaCompilationEvent;

/**
 * Keeps the headings of the groups that cannot be members at the end of the grouped lists of the
 * role and user forms, after the kinds that other extensions add in their TCA overrides: roles,
 * classic groups and groups of an unknown kind in the form of a role, groups of an unknown kind in
 * the form of a user.
 *
 * @internal
 */
final readonly class KeepNonMemberGroupsAtTheEnd
{
    #[AsEventListener('cretection/be-groups/keep-non-member-groups-at-the-end')]
    public function __invoke(AfterTcaCompilationEvent $event): void
    {
        $tca = self::moveToTheEndAt(
            $event->getTca(),
            ['be_groups', 'types', GroupKind::Role->value, 'columnsOverrides', 'subgroup', 'config', 'itemGroups'],
            [GroupKind::Role->value, GroupKind::Classic->value, UnknownKindItemGroup::GROUP],
        );
        $tca = self::moveToTheEndAt($tca, ['be_users', 'columns', 'usergroup', 'config', 'itemGroups'], [UnknownKindItemGroup::GROUP]);
        $event->setTca($tca);
    }

    /**
     * @param array<array-key, mixed> $array
     * @param list<string> $path of the item groups in $array
     * @param list<string> $keys in the order they end the item groups
     * @return array<array-key, mixed>
     */
    private static function moveToTheEndAt(array $array, array $path, array $keys): array
    {
        $key = array_shift($path);
        if ($key === null) {
            foreach ($keys as $itemGroup) {
                if (array_key_exists($itemGroup, $array)) {
                    $label = $array[$itemGroup];
                    unset($array[$itemGroup]);
                    $array[$itemGroup] = $label;
                }
            }
            return $array;
        }
        if (is_array($array[$key] ?? null)) {
            $array[$key] = self::moveToTheEndAt($array[$key], $path, $keys);
        }
        return $array;
    }
}
