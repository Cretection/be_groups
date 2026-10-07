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

namespace Cretection\BeGroups\Domain\Audit;

use Cretection\BeGroups\Configuration\ExtensionSettings;
use Cretection\BeGroups\DataHandling\RelationList;
use Cretection\BeGroups\Domain\Kind\GroupKind;
use Cretection\BeGroups\Domain\Kind\KindFieldResolver;
use Cretection\BeGroups\Domain\Kind\KindRegistry;
use Cretection\BeGroups\Domain\Repository\BackendGroupRepository;
use Cretection\BeGroups\Domain\Repository\BackendUserRepository;
use Cretection\BeGroups\Domain\Repository\DatabaseRow;
use Cretection\BeGroups\Event\AfterAuditFindingsCollectedEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Type\Bitmask\BackendGroupMountOption;

/**
 * Checks all backend groups and users against the role model.
 *
 * The DataHandler rules prevent new violations, but data written around the DataHandler
 * (SQL, synchronisation tools, the former extension) and relations that were stored before
 * the rules applied are only found by this check.
 *
 * @internal
 */
final readonly class PermissionAudit
{
    private const GROUPS_TABLE = 'be_groups';
    private const USERS_TABLE = 'be_users';

    /**
     * Permission fields on user records that TYPO3 merges with the permissions of the groups.
     * "workspace_perms" is left out on purpose: its default grants access to the live workspace.
     */
    private const USER_PERMISSION_FIELDS = [
        'userMods', 'allowed_languages', 'db_mountpoints', 'file_mountpoints', 'file_permissions',
        'category_perms', 'TSconfig', 'tsconfig_includes',
    ];

    public function __construct(
        private BackendGroupRepository $backendGroupRepository,
        private BackendUserRepository $backendUserRepository,
        private KindRegistry $kindRegistry,
        private KindFieldResolver $kindFieldResolver,
        private ExtensionSettings $extensionSettings,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * @return list<AuditFinding> errors first, then warnings, each ordered by table and uid
     */
    public function run(): array
    {
        $groups = [];
        foreach ($this->backendGroupRepository->findAll() as $group) {
            $groups[$group->getUid()] = $group;
        }

        $findings = [];
        foreach ($groups as $group) {
            $findings = [...$findings, ...$this->checkGroup($group, $groups)];
        }
        foreach ($this->backendUserRepository->findAllForAudit(self::USER_PERMISSION_FIELDS) as $user) {
            $findings = [...$findings, ...$this->checkUser($user, $groups)];
        }

        $event = new AfterAuditFindingsCollectedEvent($findings);
        $this->eventDispatcher->dispatch($event);
        $findings = $event->getFindings();
        usort($findings, static fn(AuditFinding $a, AuditFinding $b): int => [
            $a->severity === AuditSeverity::Error ? 0 : 1, $a->table, $a->uid,
        ] <=> [
            $b->severity === AuditSeverity::Error ? 0 : 1, $b->table, $b->uid,
        ]);
        return $findings;
    }

    /**
     * @param array<int, DatabaseRow> $groups all non-deleted groups, indexed by uid
     * @return list<AuditFinding>
     */
    private function checkGroup(DatabaseRow $group, array $groups): array
    {
        $kind = $group->get(GroupKind::FIELD_NAME);
        $title = $this->describe($group->get('title'), $group->get('hidden') === '1');
        $finding = fn(AuditSeverity $severity, string $identifier, string $message): AuditFinding
            => new AuditFinding($severity, $identifier, self::GROUPS_TABLE, $group->getUid(), $title, $message);

        if (!$this->kindRegistry->isKind($kind)) {
            return [$finding(
                AuditSeverity::Error,
                'unknown-kind',
                sprintf('The kind "%s" is not configured (a kind of the former extension or of an uninstalled extension). The group is neither a role nor a building block; run the upgrade wizard or assign a kind.', $kind),
            )];
        }
        if ($kind === GroupKind::Classic->value) {
            return $this->extensionSettings->isClassicGroupsAllowed()
                ? [$finding(AuditSeverity::Warning, 'classic-group', 'Classic group: split it into building blocks and a role.')]
                : [$finding(AuditSeverity::Error, 'classic-group-disabled', 'Classic groups are disabled, but this classic group still exists. Split it into building blocks and a role.')];
        }

        $findings = [];
        $members = RelationList::fromValue($group->get('subgroup'))->getUids();
        $isBuildingBlock = $this->kindRegistry->isBuildingBlock($kind);
        if ($isBuildingBlock && $members !== []) {
            $findings[] = $finding(AuditSeverity::Error, 'building-block-with-subgroups', sprintf('Building blocks must not have subgroups, but it has: %s.', implode(', ', $members)));
        }

        $foreignFields = [];
        foreach (array_keys($this->kindFieldResolver->getForeignFieldsWithEmptyValue($kind)) as $fieldName) {
            $isReportedAsSubgroups = $isBuildingBlock && $fieldName === 'subgroup';
            if (!$isReportedAsSubgroups && !$this->kindFieldResolver->isEmptyValue($fieldName, $group->get($fieldName))) {
                $foreignFields[] = $fieldName;
            }
        }
        if ($foreignFields !== []) {
            $findings[] = $finding(
                AuditSeverity::Error,
                'foreign-permissions',
                sprintf('Permissions that the kind "%s" does not show are in effect: %s. Saving the group removes them.', $kind, implode(', ', $foreignFields)),
            );
        }

        if ($kind === GroupKind::Role->value) {
            foreach ($members as $memberUid) {
                $member = $groups[$memberUid] ?? null;
                if ($member === null) {
                    $findings[] = $finding(AuditSeverity::Error, 'role-missing-member', sprintf('The role contains group %d, which does not exist.', $memberUid));
                } elseif (!$this->kindRegistry->isBuildingBlock($member->get(GroupKind::FIELD_NAME))) {
                    $findings[] = $finding(
                        AuditSeverity::Error,
                        'role-invalid-member',
                        sprintf('The role contains group %d "%s", which is no building block.', $memberUid, $member->get('title')),
                    );
                }
            }
        }
        return $findings;
    }

    /**
     * @param array<int, DatabaseRow> $groups all non-deleted groups, indexed by uid
     * @return list<AuditFinding>
     */
    private function checkUser(DatabaseRow $user, array $groups): array
    {
        if ($user->get('admin') === '1') {
            // Administrators have all permissions; the role model does not apply to them.
            return [];
        }
        $title = $this->describe($user->get('username'), $user->get('disable') === '1');
        $finding = fn(AuditSeverity $severity, string $identifier, string $message): AuditFinding
            => new AuditFinding($severity, $identifier, self::USERS_TABLE, $user->getUid(), $title, $message);

        $findings = [];
        foreach (RelationList::fromValue($user->get('usergroup'))->getUids() as $groupUid) {
            $group = $groups[$groupUid] ?? null;
            if ($group !== null && $this->kindRegistry->isBuildingBlock($group->get(GroupKind::FIELD_NAME))) {
                $findings[] = $finding(
                    AuditSeverity::Error,
                    'user-building-block',
                    sprintf('The building block %d "%s" is assigned directly; assign it through a role.', $groupUid, $group->get('title')),
                );
            }
        }

        $userPermissions = array_values(array_filter(
            self::USER_PERMISSION_FIELDS,
            fn(string $fieldName): bool => !$this->kindFieldResolver->isEmptyValue($fieldName, $user->get($fieldName), self::USERS_TABLE),
        ));
        if ($userPermissions !== []) {
            $findings[] = $finding(
                AuditSeverity::Warning,
                'user-permissions',
                sprintf('Permissions on the user record are added to those of the roles: %s. Keep user records free of permissions.', implode(', ', $userPermissions)),
            );
        }

        $mountOptions = new BackendGroupMountOption((int)$user->get('options'));
        if (!$mountOptions->shouldUserIncludePageMountsFromAssociatedGroups() || !$mountOptions->shouldUserIncludeFileMountsFromAssociatedGroups()) {
            $findings[] = $finding(
                AuditSeverity::Warning,
                'user-ignores-group-mounts',
                'The user does not use all page tree or file mounts of their groups (option "Mount from groups").',
            );
        }
        return $findings;
    }

    private function describe(string $title, bool $disabled): string
    {
        return $disabled ? $title . ' (disabled)' : $title;
    }
}
