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

namespace Cretection\BeGroups\DataHandling;

use Cretection\BeGroups\Configuration\ExtensionSettings;
use Cretection\BeGroups\Domain\Kind\GroupKind;
use Cretection\BeGroups\Domain\Kind\KindFieldResolver;
use Cretection\BeGroups\Domain\Repository\BackendGroupRepository;
use Cretection\BeGroups\Domain\Repository\BackendUserRepository;
use Cretection\BeGroups\Domain\Repository\DatabaseRow;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * Enforces the rules of the group kind model on every write through the DataHandler,
 * no matter whether it originates from a backend form, an import or the API.
 *
 * R1: A group carries values only in the fields its kind displays.
 * R2: A role consists of building blocks only.
 * R3: Building blocks have no subgroups (follows from R1).
 * R4: Users are assigned roles only (and "classic" groups while they are allowed).
 *
 * Rules never throw. They correct the incoming data or skip the record and report
 * each intervention, so imports and synchronisation tools keep working.
 *
 * @internal
 */
#[Autoconfigure(public: true)]
final readonly class GroupKindRules
{
    private const GROUPS_TABLE = 'be_groups';
    private const USERS_TABLE = 'be_users';

    public function __construct(
        private KindFieldResolver $kindFieldResolver,
        private BackendGroupRepository $backendGroupRepository,
        private BackendUserRepository $backendUserRepository,
        private ExtensionSettings $extensionSettings,
        private RuleViolationReporter $reporter,
    ) {}

    /**
     * @param array<string, mixed>|null $incomingFieldArray
     */
    public function processDatamap_preProcessFieldArray(?array &$incomingFieldArray, string $table, string|int $id, DataHandler $dataHandler): void
    {
        if ($incomingFieldArray === null) {
            return;
        }
        if ($table === self::GROUPS_TABLE && !$this->processGroup($incomingFieldArray, $id, $dataHandler)) {
            // The DataHandler skips records whose field array has been invalidated by a hook.
            $incomingFieldArray = null;
            return;
        }
        if ($table === self::USERS_TABLE) {
            $this->processUser($incomingFieldArray, $id, $dataHandler);
        }
    }

    /**
     * @param array<string, mixed> $incomingFieldArray
     * @return bool false if the record must not be written at all
     */
    private function processGroup(array &$incomingFieldArray, string|int $id, DataHandler $dataHandler): bool
    {
        $uid = $this->getUid($id);
        $currentRecord = null;
        if ($uid !== null) {
            $currentRecord = $this->backendGroupRepository->findFieldsByUid($uid, [GroupKind::FIELD_NAME, 'subgroup']);
            if ($currentRecord === null) {
                // Unknown or deleted record: the DataHandler rejects the write itself.
                return true;
            }
        }

        $kind = $this->resolveKind($incomingFieldArray, $currentRecord, $id, $dataHandler);
        if ($kind === null) {
            return false;
        }

        $this->removeForeignFieldValues($incomingFieldArray, $kind, $uid, $id, $dataHandler);
        if ($kind === GroupKind::Role->value) {
            $this->restrictRoleToBuildingBlocks($incomingFieldArray, $currentRecord, $uid, $id, $dataHandler);
        }
        return true;
    }

    /**
     * Determines the kind the record will have after saving and enforces that
     * "classic" groups cannot be created while they are disabled.
     *
     * @param array<string, mixed> $incomingFieldArray
     * @return string|null The resulting kind, or null if the record must be skipped
     */
    private function resolveKind(array &$incomingFieldArray, ?DatabaseRow $currentRecord, string|int $id, DataHandler $dataHandler): ?string
    {
        $currentKind = $currentRecord?->get(GroupKind::FIELD_NAME);
        $hasIncomingKind = array_key_exists(GroupKind::FIELD_NAME, $incomingFieldArray);
        $incomingKind = $hasIncomingKind && is_scalar($incomingFieldArray[GroupKind::FIELD_NAME])
            ? trim((string)$incomingFieldArray[GroupKind::FIELD_NAME])
            : null;

        if ($hasIncomingKind && ($incomingKind === null || !$this->isSelectableKind($incomingKind))) {
            // Unknown values are never stored; the record keeps its kind (or gets the default kind).
            unset($incomingFieldArray[GroupKind::FIELD_NAME]);
            $this->reporter->reportCorrection(
                $dataHandler->BE_USER,
                self::GROUPS_TABLE,
                $id,
                'rules.unknownKind',
                'The unknown kind "{kind}" has been ignored.',
                ['kind' => $this->describeUntrustedValue($incomingKind)],
            );
            $incomingKind = null;
        }

        $kind = $incomingKind ?? $currentKind ?? GroupKind::Classic->value;
        $isChangeToClassic = $kind === GroupKind::Classic->value && $currentKind !== GroupKind::Classic->value;
        if (!$isChangeToClassic || $this->extensionSettings->isClassicGroupsAllowed()) {
            return $kind;
        }

        if ($currentKind === null) {
            $this->reporter->reportRejection(
                $dataHandler->BE_USER,
                self::GROUPS_TABLE,
                $id,
                'rules.classicNotAllowed',
                'Creating groups of kind "classic" is disabled. The group has not been created.',
                [],
            );
            return null;
        }

        unset($incomingFieldArray[GroupKind::FIELD_NAME]);
        $this->reporter->reportRejection(
            $dataHandler->BE_USER,
            self::GROUPS_TABLE,
            $id,
            'rules.classicNotAllowed',
            'Changing groups to kind "classic" is disabled. The group keeps its kind.',
            [],
        );
        return $currentKind;
    }

    /**
     * R1 + R3: Empties every permission field the kind does not display, both in the
     * incoming data and in the stored record. TCA default values of new records are
     * overridden as well (e.g. the default file permissions of be_groups).
     *
     * @param array<string, mixed> $incomingFieldArray
     */
    private function removeForeignFieldValues(array &$incomingFieldArray, string $kind, ?int $uid, string|int $id, DataHandler $dataHandler): void
    {
        $foreignFields = $this->kindFieldResolver->getForeignFieldsWithEmptyValue($kind);
        if ($foreignFields === []) {
            return;
        }

        $storedValues = $uid === null ? [] : ($this->backendGroupRepository->findFieldsByUid($uid, array_keys($foreignFields))->values ?? []);
        $removedFields = [];
        foreach ($foreignFields as $fieldName => $emptyValue) {
            if ($this->hasValue($incomingFieldArray[$fieldName] ?? null) || $this->hasValue($storedValues[$fieldName] ?? null)) {
                $removedFields[] = $fieldName;
            }
            $incomingFieldArray[$fieldName] = $emptyValue;
        }

        if ($removedFields !== []) {
            $this->reporter->reportCorrection(
                $dataHandler->BE_USER,
                self::GROUPS_TABLE,
                $id,
                'rules.foreignFieldsRemoved',
                'Fields not belonging to kind "{kind}" have been emptied: {fields}',
                ['kind' => $kind, 'fields' => implode(', ', $removedFields)],
            );
        }
    }

    /**
     * R2: Newly added subgroups of a role must be existing building blocks.
     * Already stored references are kept and reported by the audit instead.
     *
     * @param array<string, mixed> $incomingFieldArray
     */
    private function restrictRoleToBuildingBlocks(array &$incomingFieldArray, ?DatabaseRow $currentRecord, ?int $uid, string|int $id, DataHandler $dataHandler): void
    {
        if (!array_key_exists('subgroup', $incomingFieldArray)) {
            return;
        }
        $incoming = UidList::fromValue($incomingFieldArray['subgroup']);
        $stored = UidList::fromValue($currentRecord?->get('subgroup') ?? '');
        $addedUids = $incoming->withoutUidsOf($stored);
        if ($addedUids === []) {
            $incomingFieldArray['subgroup'] = $incoming->toString();
            return;
        }

        $kinds = $this->backendGroupRepository->findKindsByUids($addedUids);
        $rejectedUids = [];
        foreach ($addedUids as $addedUid) {
            $isSelfReference = $addedUid === $uid;
            if ($isSelfReference || !GroupKind::isBuildingBlock($kinds[$addedUid] ?? '')) {
                $rejectedUids[] = $addedUid;
            }
        }

        $incomingFieldArray['subgroup'] = $incoming->without($rejectedUids)->toString();
        if ($rejectedUids !== []) {
            $this->reporter->reportCorrection(
                $dataHandler->BE_USER,
                self::GROUPS_TABLE,
                $id,
                'rules.subgroupsRejected',
                'Only building blocks can be added to a role. Rejected groups: {uids}',
                ['uids' => implode(', ', $rejectedUids)],
            );
        }
    }

    /**
     * R4: Newly assigned groups of a user must be roles (or "classic" groups while allowed).
     * Already stored assignments are kept, so switching off "classic" groups never removes access.
     *
     * @param array<string, mixed> $incomingFieldArray
     */
    private function processUser(array &$incomingFieldArray, string|int $id, DataHandler $dataHandler): void
    {
        if (!array_key_exists('usergroup', $incomingFieldArray)) {
            return;
        }
        $uid = $this->getUid($id);
        $incoming = UidList::fromValue($incomingFieldArray['usergroup']);
        $stored = UidList::fromValue($uid === null ? '' : ($this->backendUserRepository->findUsergroupListByUid($uid) ?? ''));
        $addedUids = $incoming->withoutUidsOf($stored);
        if ($addedUids === []) {
            return;
        }

        $assignableKinds = [GroupKind::Role->value];
        if ($this->extensionSettings->isClassicGroupsAllowed()) {
            $assignableKinds[] = GroupKind::Classic->value;
        }
        $kinds = $this->backendGroupRepository->findKindsByUids($addedUids);
        $rejectedUids = [];
        foreach ($addedUids as $addedUid) {
            if (!in_array($kinds[$addedUid] ?? '', $assignableKinds, true)) {
                $rejectedUids[] = $addedUid;
            }
        }
        if ($rejectedUids === []) {
            return;
        }

        // The order of the usergroup list matters (TSconfig inheritance), so only rejected entries are removed.
        $incomingFieldArray['usergroup'] = $incoming->without($rejectedUids)->toString();
        $this->reporter->reportCorrection(
            $dataHandler->BE_USER,
            self::USERS_TABLE,
            $id,
            'rules.usergroupsRejected',
            'Only roles can be assigned to users. Rejected groups: {uids}',
            ['uids' => implode(', ', $rejectedUids)],
        );
    }

    /**
     * Untrusted input is only repeated in messages and logs if it looks like a kind identifier.
     */
    private function describeUntrustedValue(?string $value): string
    {
        return $value !== null && preg_match('/^[a-z0-9_]{1,64}$/', $value) === 1 ? $value : '(invalid value)';
    }

    private function isSelectableKind(string $kind): bool
    {
        return $kind === GroupKind::Classic->value || $this->kindFieldResolver->isKnownKind($kind);
    }

    private function hasValue(mixed $value): bool
    {
        if (is_array($value)) {
            return $value !== [];
        }
        return $value !== null && $value !== '' && $value !== '0' && $value !== 0;
    }

    private function getUid(string|int $id): ?int
    {
        return MathUtility::canBeInterpretedAsInteger($id) ? (int)$id : null;
    }
}
