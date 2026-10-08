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
use Cretection\BeGroups\Domain\Kind\KindPrefix;
use Cretection\BeGroups\Domain\Kind\KindRegistry;
use Cretection\BeGroups\Domain\Repository\BackendGroupRepository;
use Cretection\BeGroups\Domain\Repository\BackendUserRepository;
use Cretection\BeGroups\Domain\Repository\DatabaseRow;
use Cretection\BeGroups\Event\ModifyKindOfNewGroupEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Schema\TcaSchemaFactory;
use TYPO3\CMS\Core\Utility\MathUtility;

/**
 * Enforces the rules of the group kind model on every write through the DataHandler,
 * no matter whether it originates from a backend form, an import or the API.
 *
 * R1: A group carries permissions only in the fields its kind displays.
 * R2: A role only gets building blocks added.
 * R3: Building blocks have no subgroups (follows from R1).
 * R4: Users only get roles assigned (and "classic" groups while they are allowed).
 *
 * Relations that are already stored are never removed by R2 and R4, so switching off
 * "classic" groups or migrating never takes away access. Rules never throw: they correct
 * the incoming data or skip the record. Corrections are reported after all operations,
 * so nothing is logged for a save that is aborted (e.g. by the sudo mode).
 *
 * A new instance is created for every DataHandler run.
 *
 * @internal
 */
#[Autoconfigure(public: true, shared: false)]
final class GroupKindRules
{
    private const GROUPS_TABLE = 'be_groups';
    private const USERS_TABLE = 'be_users';

    /**
     * @var list<array{table: string, id: string|int, labelKey: string, logMessage: string, arguments: array<string, string>, information: bool}>
     */
    private array $pendingCorrections = [];

    /**
     * The kinds of new groups without kind, by NEW placeholder: the rules of a role that refers to a
     * new group and the new group itself must be evaluated with the same kind.
     *
     * @var array<string, string>
     */
    private array $kindsOfNewGroups = [];

    public function __construct(
        private readonly KindRegistry $kindRegistry,
        private readonly KindFieldResolver $kindFieldResolver,
        private readonly BackendGroupRepository $backendGroupRepository,
        private readonly BackendUserRepository $backendUserRepository,
        private readonly ExtensionSettings $extensionSettings,
        private readonly RuleViolationReporter $reporter,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly KindPrefix $kindPrefix,
        private readonly TcaSchemaFactory $tcaSchemaFactory,
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

    public function processDatamap_afterAllOperations(DataHandler $dataHandler): void
    {
        foreach ($this->pendingCorrections as $correction) {
            $report = $correction['information'] ? $this->reporter->reportInformation(...) : $this->reporter->reportCorrection(...);
            $report(
                $dataHandler->BE_USER,
                $correction['table'],
                $correction['id'],
                $correction['labelKey'],
                $correction['logMessage'],
                $correction['arguments'],
            );
        }
        $this->pendingCorrections = [];
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
                // The record does not exist at all: the DataHandler rejects the write itself.
                return true;
            }
        }

        $kind = $this->resolveKind($incomingFieldArray, $currentRecord, $id, $dataHandler);
        if ($kind === null) {
            return false;
        }

        $this->removeForeignFieldValues($incomingFieldArray, $kind, $uid, $id);
        if ($kind === GroupKind::Role->value) {
            $this->rejectAddedRelations(
                $incomingFieldArray,
                'subgroup',
                $currentRecord?->get('subgroup') ?? '',
                fn(?string $memberKind): bool => $memberKind !== null && $this->kindRegistry->isBuildingBlock($memberKind),
                [$id, $uid],
                $dataHandler,
                self::GROUPS_TABLE,
                $id,
                'rules.subgroupsRejected',
                'Only building blocks can be added to a role. Rejected groups: {groups}',
                $kind,
            );
        }
        return true;
    }

    /**
     * Determines the kind the record has after saving and makes sure the DataHandler stores
     * exactly this kind. Unknown kinds are ignored; "classic" groups cannot be created
     * while they are disabled.
     *
     * @param array<string, mixed> $incomingFieldArray
     * @return string|null The resulting kind, or null if the record must be skipped
     */
    private function resolveKind(array &$incomingFieldArray, ?DatabaseRow $currentRecord, string|int $id, DataHandler $dataHandler): ?string
    {
        $currentKind = $currentRecord?->get(GroupKind::FIELD_NAME);
        $incomingKind = null;
        if (array_key_exists(GroupKind::FIELD_NAME, $incomingFieldArray)) {
            $rawKind = $incomingFieldArray[GroupKind::FIELD_NAME];
            $candidate = is_scalar($rawKind) ? trim((string)$rawKind) : '';
            if ($candidate === $currentKind || $this->kindRegistry->isKind($candidate)) {
                $incomingKind = $candidate;
            } else {
                unset($incomingFieldArray[GroupKind::FIELD_NAME]);
                $this->addCorrection(
                    self::GROUPS_TABLE,
                    $id,
                    'rules.unknownKind',
                    'The unknown kind "{kind}" has been ignored.',
                    ['kind' => $this->describeUntrustedValue($candidate)],
                );
            }
        }

        if ($currentKind === null) {
            $kind = $incomingKind ?? $this->getKindOfNewGroup((string)$id, $incomingFieldArray, $dataHandler);
            if ($kind === GroupKind::Classic->value && !$this->extensionSettings->isClassicGroupsAllowed()) {
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
            // The rules are evaluated for this kind, so the DataHandler must store exactly this kind.
            $incomingFieldArray[GroupKind::FIELD_NAME] = $kind;
            return $kind;
        }

        $kind = $incomingKind ?? $currentKind;
        if ($kind === GroupKind::Classic->value && $currentKind !== GroupKind::Classic->value && !$this->extensionSettings->isClassicGroupsAllowed()) {
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
        return $kind;
    }

    /**
     * The kind a new record gets when the datamap contains none: the default kind, which listeners
     * of ModifyKindOfNewGroupEvent may replace by another configured kind.
     *
     * @param array<array-key, mixed> $fieldArray
     */
    private function getKindOfNewGroup(string $placeholder, array $fieldArray, DataHandler $dataHandler): string
    {
        if (!isset($this->kindsOfNewGroups[$placeholder])) {
            $defaultKind = $this->getDefaultKind($fieldArray, $dataHandler);
            $event = new ModifyKindOfNewGroupEvent($fieldArray, $defaultKind);
            $this->eventDispatcher->dispatch($event);
            $this->kindsOfNewGroups[$placeholder] = $this->kindRegistry->isKind($event->getKind()) ? $event->getKind() : $defaultKind;
        }
        return $this->kindsOfNewGroups[$placeholder];
    }

    /**
     * The default kind of a new record, determined in the same order as the DataHandler does:
     * TCA default, overridden by user TSconfig, overridden by page TSconfig.
     *
     * @param array<array-key, mixed> $fieldArray
     */
    private function getDefaultKind(array $fieldArray, DataHandler $dataHandler): string
    {
        $candidates = [
            $this->kindRegistry->getDefaultKind(),
            $this->getKindFromTcaDefaults($dataHandler->BE_USER->getTSConfig()),
            $this->getKindFromTcaDefaults(BackendUtility::getPagesTSconfig($this->getPageUid($fieldArray))),
        ];
        $kind = GroupKind::Classic->value;
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $this->kindRegistry->isKind($candidate)) {
                $kind = $candidate;
            }
        }
        return $kind;
    }

    /**
     * @param array<mixed> $tsConfig
     */
    private function getKindFromTcaDefaults(array $tsConfig): ?string
    {
        $tables = $tsConfig['TCAdefaults.'] ?? null;
        $fields = is_array($tables) ? ($tables[self::GROUPS_TABLE . '.'] ?? null) : null;
        $kind = is_array($fields) ? ($fields[GroupKind::FIELD_NAME] ?? null) : null;
        return is_string($kind) ? $kind : null;
    }

    /**
     * R1 + R3: Empties every permission field the kind does not display, both in the incoming data
     * and in the stored record. New records are covered completely, so TCA and TSconfig default
     * values (e.g. the default file permissions) never end up in a building block.
     *
     * @param array<string, mixed> $incomingFieldArray
     */
    private function removeForeignFieldValues(array &$incomingFieldArray, string $kind, ?int $uid, string|int $id): void
    {
        $foreignFields = $this->kindFieldResolver->getForeignFieldsWithEmptyValue($kind);
        if ($foreignFields === []) {
            return;
        }

        $storedValues = $uid === null ? [] : ($this->backendGroupRepository->findFieldsByUid($uid, array_keys($foreignFields))->values ?? []);
        $removedFields = [];
        foreach ($foreignFields as $fieldName => $emptyValue) {
            $incomingHasValue = array_key_exists($fieldName, $incomingFieldArray)
                && !$this->kindFieldResolver->isEmptyValue($fieldName, $incomingFieldArray[$fieldName]);
            // A stored value the caller empties itself is no correction of the rules.
            $storedHasValue = !array_key_exists($fieldName, $incomingFieldArray)
                && array_key_exists($fieldName, $storedValues)
                && !$this->kindFieldResolver->isEmptyValue($fieldName, $storedValues[$fieldName]);
            if ($incomingHasValue || $storedHasValue) {
                $removedFields[] = $fieldName;
            }
            if ($uid === null || $incomingHasValue || $storedHasValue || array_key_exists($fieldName, $incomingFieldArray)) {
                $incomingFieldArray[$fieldName] = $emptyValue;
            }
        }

        if ($removedFields !== []) {
            $this->addCorrection(
                self::GROUPS_TABLE,
                $id,
                'rules.foreignFieldsRemoved',
                'Fields not belonging to kind "{kind}" have been emptied: {fields}',
                ['kind' => $kind, 'fields' => implode(', ', $removedFields)],
                true,
            );
        }
    }

    /**
     * R4: Newly assigned groups of a user must be roles (or "classic" groups while allowed).
     *
     * @param array<string, mixed> $incomingFieldArray
     */
    private function processUser(array &$incomingFieldArray, string|int $id, DataHandler $dataHandler): void
    {
        $uid = $this->getUid($id);
        $classicGroupsAllowed = $this->extensionSettings->isClassicGroupsAllowed();
        $this->rejectAddedRelations(
            $incomingFieldArray,
            'usergroup',
            $uid === null ? '' : ($this->backendUserRepository->findUsergroupListByUid($uid) ?? ''),
            static fn(?string $groupKind): bool => $groupKind === GroupKind::Role->value
                || ($classicGroupsAllowed && $groupKind === GroupKind::Classic->value),
            [],
            $dataHandler,
            self::USERS_TABLE,
            $id,
            'rules.usergroupsRejected',
            'Only roles can be assigned to users. Rejected groups: {groups}',
            $this->getRecordType(self::USERS_TABLE, $incomingFieldArray),
        );
    }

    /**
     * Removes newly added relations to groups whose kind is not accepted. Stored relations are kept
     * and the order of the list is preserved (it matters for the TSconfig inheritance).
     *
     * @param array<string, mixed> $incomingFieldArray
     * @param \Closure(?string): bool $isAcceptedKind
     * @param list<int|string|null> $selfReferences entries that would reference the record itself
     * @param string|null $recordType the type of the record, for type-specific defaults
     */
    private function rejectAddedRelations(
        array &$incomingFieldArray,
        string $fieldName,
        string $storedValue,
        \Closure $isAcceptedKind,
        array $selfReferences,
        DataHandler $dataHandler,
        string $table,
        string|int $id,
        string $labelKey,
        string $logMessage,
        ?string $recordType,
    ): void {
        if (!array_key_exists($fieldName, $incomingFieldArray)) {
            // A new record gets the default of TCA and TSconfig (TCAdefaults) only after this hook, so the
            // default is written explicitly and checked like any other value.
            $defaultValue = $this->getUid($id) === null ? $this->findDefaultValue($table, $fieldName, $incomingFieldArray, $recordType, $dataHandler) : '';
            if ($defaultValue === '') {
                return;
            }
            $incomingFieldArray[$fieldName] = $defaultValue;
        }
        $incoming = RelationList::fromValue($incomingFieldArray[$fieldName]);
        $addedEntries = $incoming->getEntriesMissingIn(RelationList::fromStoredValue($storedValue));
        $kinds = $this->resolveKinds($addedEntries, $dataHandler);

        $rejectedEntries = [];
        foreach ($addedEntries as $entry) {
            if (in_array($entry, $selfReferences, true) || !$isAcceptedKind($kinds[$entry] ?? null)) {
                $rejectedEntries[] = $entry;
            }
        }
        $incomingFieldArray[$fieldName] = $incoming->without($rejectedEntries)->toString();

        if ($rejectedEntries !== []) {
            $this->addCorrection($table, $id, $labelKey, $logMessage, [
                'uids' => implode(', ', $rejectedEntries),
                'groups' => implode(', ', array_map(
                    fn(int|string $entry): string => $this->describeGroup($entry, $kinds[$entry] ?? '', $dataHandler),
                    $rejectedEntries,
                )),
            ]);
        }
    }

    /**
     * The value the DataHandler gives a field of a new record that does not set it: the default of
     * TCA, overruled by TCAdefaults of user TSconfig, of page TSconfig and type-specific TCAdefaults
     * ("TCAdefaults.be_users.usergroup.types.0"). Like the DataHandler, only fields of the record
     * type get a default.
     *
     * @param array<string, mixed> $fieldArray
     */
    private function findDefaultValue(string $table, string $fieldName, array $fieldArray, ?string $recordType, DataHandler $dataHandler): string
    {
        if (!$this->tcaSchemaFactory->has($table)) {
            return '';
        }
        $schema = $this->tcaSchemaFactory->get($table);
        if ($recordType !== null && $schema->hasSubSchema($recordType)) {
            $schema = $schema->getSubSchema($recordType);
        }
        if (!$schema->hasField($fieldName)) {
            return '';
        }
        $value = $schema->getField($fieldName)->getDefaultValue();
        $tsConfigs = [$dataHandler->BE_USER->getTSConfig(), BackendUtility::getPagesTSconfig($this->getPageUid($fieldArray))];
        $paths = [[$fieldName]];
        if ($recordType !== null) {
            $paths[] = [$fieldName . '.', 'types.', $recordType];
        }
        foreach ($paths as $path) {
            foreach ($tsConfigs as $tsConfig) {
                $candidate = $this->readTsConfig($tsConfig, ['TCAdefaults.', $table . '.', ...$path]);
                $value = is_scalar($candidate) ? $candidate : $value;
            }
        }
        return is_scalar($value) ? (string)$value : '';
    }

    /**
     * @param array<mixed> $tsConfig
     * @param list<string> $path
     */
    private function readTsConfig(array $tsConfig, array $path): mixed
    {
        $value = $tsConfig;
        foreach ($path as $key) {
            if (!is_array($value) || !array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }
        return $value;
    }

    /**
     * @param array<string, mixed> $fieldArray
     */
    private function getRecordType(string $table, array $fieldArray): ?string
    {
        if (!$this->tcaSchemaFactory->has($table) || !$this->tcaSchemaFactory->get($table)->supportsSubSchema()) {
            return null;
        }
        $schema = $this->tcaSchemaFactory->get($table);
        $typeField = $schema->getSubSchemaTypeInformation()->getFieldName();
        $type = $fieldArray[$typeField] ?? ($schema->hasField($typeField) ? $schema->getField($typeField)->getDefaultValue() : null);
        return is_scalar($type) ? (string)$type : null;
    }

    /**
     * A negative pid means "after record", so only a positive pid is a page.
     *
     * @param array<array-key, mixed> $fieldArray
     */
    private function getPageUid(array $fieldArray): int
    {
        $pageUid = filter_var($fieldArray['pid'] ?? 0, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return is_int($pageUid) ? $pageUid : 0;
    }

    /**
     * Describes a referenced group for messages the way TYPO3 shows it, e.g. "META: Editors [12]".
     * Only stored titles are used; a group that is not stored is named by its placeholder, which
     * RelationList has validated.
     */
    private function describeGroup(int|string $entry, string $kind, DataHandler $dataHandler): string
    {
        $uid = is_int($entry) ? $entry : ($dataHandler->substNEWwithIDs[$entry] ?? null);
        if (!is_int($uid)) {
            return mb_strimwidth($entry, 0, 64, '…');
        }
        $title = $this->backendGroupRepository->findFieldsByUid($uid, ['title'])?->get('title') ?? '';
        return $title === '' ? (string)$uid : sprintf('%s [%d]', mb_strimwidth($this->kindPrefix->prefixTitle($kind, $title), 0, 100, '…'), $uid);
    }

    /**
     * Resolves the kind of referenced groups: stored groups by their uid, groups created in the
     * same datamap by their NEW placeholder. Unresolvable references get no kind.
     *
     * @param list<int|string> $entries
     * @return array<int|string, string>
     */
    private function resolveKinds(array $entries, DataHandler $dataHandler): array
    {
        $uidsByEntry = [];
        $kinds = [];
        foreach ($entries as $entry) {
            if (is_int($entry)) {
                $uidsByEntry[$entry] = $entry;
                continue;
            }
            if (!$this->isPlaceholderOfGroupOnly($entry, $dataHandler)) {
                continue;
            }
            $substitutedUid = $dataHandler->substNEWwithIDs[$entry] ?? null;
            if (is_int($substitutedUid)) {
                $uidsByEntry[$entry] = $substitutedUid;
                continue;
            }
            $pendingRecord = $dataHandler->datamap[self::GROUPS_TABLE][$entry] ?? null;
            if (is_array($pendingRecord)) {
                $pendingKind = $pendingRecord[GroupKind::FIELD_NAME] ?? null;
                $kinds[$entry] = is_string($pendingKind) && $this->kindRegistry->isKind($pendingKind)
                    ? $pendingKind
                    : $this->getKindOfNewGroup($entry, $pendingRecord, $dataHandler);
            }
        }

        $storedKinds = $this->backendGroupRepository->findKindsByUids(array_values($uidsByEntry));
        foreach ($uidsByEntry as $entry => $uid) {
            if (isset($storedKinds[$uid])) {
                $kinds[$entry] = $storedKinds[$uid];
            }
        }
        return $kinds;
    }

    /**
     * Whether a NEW placeholder stands for a group of the datamap and nothing else. The DataHandler
     * maps placeholders to uids regardless of the table, and the last record created wins: a
     * placeholder that is also used in another table can point to a different record when the
     * relation is checked than when it is finally stored.
     */
    private function isPlaceholderOfGroupOnly(string $placeholder, DataHandler $dataHandler): bool
    {
        foreach ($dataHandler->datamap as $table => $records) {
            if ($table !== self::GROUPS_TABLE && array_key_exists($placeholder, $records)) {
                return false;
            }
        }
        return is_array($dataHandler->datamap[self::GROUPS_TABLE] ?? null) && array_key_exists($placeholder, $dataHandler->datamap[self::GROUPS_TABLE]);
    }

    /**
     * @param array<string, string> $arguments
     */
    private function addCorrection(string $table, string|int $id, string $labelKey, string $logMessage, array $arguments, bool $information = false): void
    {
        $this->pendingCorrections[] = [
            'table' => $table,
            'id' => $id,
            'labelKey' => $labelKey,
            'logMessage' => $logMessage,
            'arguments' => $arguments,
            'information' => $information,
        ];
    }

    /**
     * Untrusted input is only repeated in messages and logs if it looks like a kind identifier.
     */
    private function describeUntrustedValue(string $value): string
    {
        return preg_match('/^[A-Za-z0-9_-]{1,64}$/', $value) === 1 ? $value : '(invalid value)';
    }

    private function getUid(string|int $id): ?int
    {
        return MathUtility::canBeInterpretedAsInteger($id) ? (int)$id : null;
    }
}
