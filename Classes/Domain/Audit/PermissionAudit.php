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
use Cretection\BeGroups\Domain\Kind\KindPrefix;
use Cretection\BeGroups\Domain\Kind\KindRegistry;
use Cretection\BeGroups\Domain\Repository\BackendGroupRepository;
use Cretection\BeGroups\Domain\Repository\BackendUserRepository;
use Cretection\BeGroups\Domain\Repository\DatabaseRow;
use Cretection\BeGroups\Event\AfterAuditFindingsCollectedEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Type\Bitmask\BackendGroupMountOption;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Checks all backend groups and users against the role model.
 *
 * The DataHandler rules prevent new violations, but data written around the DataHandler
 * (SQL, synchronisation tools) and relations that were stored before
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
     * "workspace_perms" is added only with workspaces (see getUserPermissionFields()).
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
        private TcaSchemaFactory $tcaSchemaFactory,
        private KindPrefix $kindPrefix,
    ) {}

    /**
     * @return list<AuditFinding> errors first, then warnings, each ordered by table and uid
     */
    public function run(): array
    {
        $groups = [];
        // The titles of the groups as the backend shows them: with the prefix of their kind.
        $titles = [];
        foreach ($this->backendGroupRepository->findAll() as $group) {
            $groups[$group->getUid()] = $group;
            $titles[$group->getUid()] = $this->kindPrefix->prefixTitle($group->get(GroupKind::FIELD_NAME), $group->get('title'));
        }

        $findings = [];
        foreach ($groups as $uid => $group) {
            $findings = [...$findings, ...$this->checkGroup($group, $groups, $titles), ...$this->checkList(self::GROUPS_TABLE, $group, 'subgroup', $titles[$uid])];
        }
        $userPermissionFields = $this->getUserPermissionFields();
        foreach ($this->backendUserRepository->findAllForAudit($userPermissionFields) as $user) {
            if ($user->get('admin') === '1') {
                // Administrators have all permissions; the role model does not apply to them.
                continue;
            }
            $findings = [...$findings, ...$this->checkUser($user, $groups, $titles, $userPermissionFields), ...$this->checkList(self::USERS_TABLE, $user, 'usergroup', $user->get('username'))];
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
     * @param array<int, string> $titles the titles of these groups with the prefix of their kind
     * @return list<AuditFinding>
     */
    private function checkGroup(DatabaseRow $group, array $groups, array $titles): array
    {
        $kind = $group->get(GroupKind::FIELD_NAME);
        $title = $this->describe($titles[$group->getUid()], $group->get('hidden') === '1');
        $finding = fn(AuditSeverity $severity, string $identifier, string $message): AuditFinding
            => new AuditFinding($severity, $identifier, self::GROUPS_TABLE, $group->getUid(), $title, $message);

        if (!$this->kindRegistry->isKind($kind)) {
            return [$finding(
                AuditSeverity::Error,
                'unknown-kind',
                match ($kind) {
                    '' => 'The group has no kind (e.g. written by SQL). It is neither a role nor a building block; assign a kind.',
                    // Unlike the fields of an uninstalled extension, workspace_perms stays a managed field of the core:
                    // another kind clears it, so its users lack live access once EXT:workspaces is installed again.
                    GroupKind::Workspace->value => 'The kind "workspace" (Workspace building blocks) needs EXT:workspaces. Without it, the group is neither a role nor a building block, '
                        . 'and TYPO3 ignores its access to the live workspace (workspace_perms), as every user works in the live workspace. '
                        . 'Keep the group if EXT:workspaces may be installed again: choosing another kind clears that access, which its users then lack. '
                        . 'If you no longer use workspaces, remove it from its roles and delete it.',
                    default => sprintf('The kind "%s" is not configured or has no type of its own (e.g. the kind of an uninstalled extension, or written by SQL). The group is neither a role nor a building block; assign a kind.', $kind),
                },
            )];
        }
        if ($kind === GroupKind::Classic->value) {
            return $this->extensionSettings->isClassicGroupsAllowed()
                ? [$finding(AuditSeverity::Warning, 'classic-group', 'Classic group: convert it into a role or building blocks. begroups:classify shows how, or what needs your decision.')]
                : [$finding(AuditSeverity::Error, 'classic-group-disabled', 'Classic groups are disabled, but this classic group still exists. Convert it into a role or building blocks: begroups:classify shows how, or what needs your decision.')];
        }

        $findings = [];
        $members = self::readGroupList($group->get('subgroup'));
        $isBuildingBlock = $this->kindRegistry->isBuildingBlock($kind);
        if ($isBuildingBlock && $members !== []) {
            $findings[] = $finding(AuditSeverity::Error, 'building-block-with-subgroups', sprintf(
                'Building blocks must not have subgroups, but it has: %s.',
                implode(', ', array_map(static fn(int $uid): string => 'group ' . self::nameGroup($uid, $titles), $members)),
            ));
        }

        $foreignFields = [];
        foreach (array_keys($this->kindFieldResolver->getForeignFieldsWithEmptyValue($kind)) as $fieldName) {
            $isReportedAsSubgroups = $isBuildingBlock && $fieldName === 'subgroup';
            if (!$isReportedAsSubgroups && !$this->kindFieldResolver->isEmptyValue($fieldName, $group->get($fieldName))) {
                $foreignFields[] = $fieldName;
            }
        }
        // Without EXT:workspaces, every user works in the live workspace and TYPO3 ignores workspace_perms,
        // until EXT:workspaces is installed again.
        $hasLiveWorkspaceWithoutEffect = in_array('workspace_perms', $foreignFields, true) && !$this->tcaSchemaFactory->has('sys_workspace');
        if ($hasLiveWorkspaceWithoutEffect) {
            $foreignFields = array_diff($foreignFields, ['workspace_perms']);
        }
        if ($foreignFields !== []) {
            $findings[] = $finding(
                AuditSeverity::Error,
                'foreign-permissions',
                sprintf('Permissions that the kind "%s" does not show are in effect: %s. Saving the group removes them.', $kind, implode(', ', $foreignFields)),
            );
        }
        if ($hasLiveWorkspaceWithoutEffect) {
            $findings[] = $finding(
                AuditSeverity::Error,
                'foreign-permissions',
                sprintf(
                    'The group allows editing in the live workspace (workspace_perms), which the kind "%s" does not show. This has no effect while EXT:workspaces is not installed, but takes effect again once it is installed. Saving the group removes it.',
                    $kind,
                ),
            );
        }

        // Only the form of classic groups shows these fields, or the forms of all kinds do
        $unassignedFields = array_values(array_filter(
            $this->kindFieldResolver->getUnassignedFieldNames(),
            fn(string $fieldName): bool => !$this->kindFieldResolver->isEmptyValue($fieldName, $group->get($fieldName)),
        ));
        if ($unassignedFields !== []) {
            $findings[] = $finding(
                AuditSeverity::Warning,
                'unassigned-fields',
                sprintf(
                    'Fields that belong to no kind have values, so the kind "%s" does not restrict them: %s. If they grant permissions, assign these fields to a kind.',
                    $kind,
                    implode(', ', $unassignedFields),
                ),
            );
        }

        if ($kind === GroupKind::Role->value) {
            foreach ($members as $memberUid) {
                $member = $groups[$memberUid] ?? null;
                if ($member === null) {
                    $findings[] = $finding(AuditSeverity::Error, 'role-missing-member', sprintf('The role contains group %d, which does not exist or is deleted.', $memberUid));
                    continue;
                }
                $memberKind = $member->get(GroupKind::FIELD_NAME);
                if ($this->kindRegistry->isBuildingBlock($memberKind)) {
                    continue;
                }
                $memberName = self::nameGroup($memberUid, $titles);
                $findings[] = $finding(
                    AuditSeverity::Error,
                    'role-invalid-member',
                    sprintf('The role contains %s; only building blocks belong in a role.', match (true) {
                        $memberKind === '' => sprintf('group %s without a kind', $memberName),
                        !$this->kindRegistry->isKind($memberKind) => sprintf('group %s of the unknown kind "%s"', $memberName, $memberKind),
                        $memberKind === GroupKind::Role->value => 'the role ' . $memberName,
                        default => 'the classic group ' . $memberName,
                    }),
                );
            }
        }
        return $findings;
    }

    /**
     * @param array<int, DatabaseRow> $groups all non-deleted groups, indexed by uid
     * @param array<int, string> $titles the titles of these groups with the prefix of their kind
     * @param list<string> $userPermissionFields
     * @return list<AuditFinding>
     */
    private function checkUser(DatabaseRow $user, array $groups, array $titles, array $userPermissionFields): array
    {
        $title = $this->describe($user->get('username'), $user->get('disable') === '1');
        $finding = fn(AuditSeverity $severity, string $identifier, string $message): AuditFinding
            => new AuditFinding($severity, $identifier, self::USERS_TABLE, $user->getUid(), $title, $message);

        $findings = [];
        foreach (self::readGroupList($user->get('usergroup')) as $groupUid) {
            $group = $groups[$groupUid] ?? null;
            if ($group !== null && $this->kindRegistry->isBuildingBlock($group->get(GroupKind::FIELD_NAME))) {
                $findings[] = $finding(
                    AuditSeverity::Error,
                    'user-building-block',
                    sprintf('The building block %s is assigned directly; assign it through a role.', self::nameGroup($groupUid, $titles)),
                );
            }
        }

        $userPermissions = array_values(array_filter(
            $userPermissionFields,
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

    /**
     * The permission fields of user records. Without workspaces, every user works in the live workspace
     * and TYPO3 ignores "workspace_perms", whose default is 1 then. With workspaces, its default is 0,
     * and a set value grants access to the live workspace outside the roles.
     *
     * @return list<string>
     */
    private function getUserPermissionFields(): array
    {
        return $this->tcaSchemaFactory->has('sys_workspace')
            ? [...self::USER_PERMISSION_FIELDS, 'workspace_perms']
            : self::USER_PERMISSION_FIELDS;
    }

    /**
     * Reports lists of groups that TYPO3 and the DataHandler read differently (see RelationList::isCanonical()).
     * Spaces, empty entries and "0" (no group) are left out: TYPO3, the backend form and the DataHandler
     * ignore them alike, and the form of a building block, which has no subgroups, could not remove them.
     *
     * @return list<AuditFinding>
     */
    private function checkList(string $table, DatabaseRow $record, string $fieldName, string $title): array
    {
        $entries = array_filter(
            GeneralUtility::trimExplode(',', $record->get($fieldName), true),
            static fn(string $entry): bool => $entry !== '0',
        );
        if (RelationList::isCanonical(implode(',', $entries))) {
            return [];
        }
        $disabledField = $table === self::USERS_TABLE ? 'disable' : 'hidden';
        return [new AuditFinding(
            AuditSeverity::Warning,
            'unclean-group-list',
            $table,
            $record->getUid(),
            $this->describe($title, $record->get($disabledField) === '1'),
            sprintf(
                'The field "%s" ("%s") contains duplicates or entries that TYPO3 and the backend form read differently, e.g. "be_groups_5" or "05". Saving the record in the backend can add or remove groups; check them first.',
                $fieldName,
                $record->get($fieldName),
            ),
        )];
    }

    /**
     * Reads a stored list of groups like TYPO3 does: entries that are no number become 0 and match no group.
     *
     * @return list<int>
     */
    private static function readGroupList(string $list): array
    {
        return array_values(array_unique(array_filter(GeneralUtility::intExplode(',', $list, true), static fn(int $uid): bool => $uid > 0)));
    }

    /**
     * Names a group by its uid and title, e.g. '3 "META: Editors"', or by its uid only if it does not exist.
     *
     * @param array<int, string> $titles
     */
    private static function nameGroup(int $uid, array $titles): string
    {
        return isset($titles[$uid]) ? sprintf('%d "%s"', $uid, $titles[$uid]) : (string)$uid;
    }

    private function describe(string $title, bool $disabled): string
    {
        return $disabled ? $title . ' (disabled)' : $title;
    }
}
