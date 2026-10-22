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

namespace Cretection\BeGroups\Tests\Functional\Documentation;

use Cretection\BeGroups\DataHandling\GroupKindRules;
use Cretection\BeGroups\DataHandling\RuleViolationReporter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * The rules report as the manual says (Concept, "Rules"): permission values that do not belong to
 * the kind are emptied and reported as information, also when the caller sends them without
 * changing the kind, while rejected relations are user errors.
 */
#[CoversClass(GroupKindRules::class)]
#[CoversClass(RuleViolationReporter::class)]
final class ReportsOfTheRulesTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['cretection/be-groups'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/ReportsOfTheRules.csv');
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
    }

    #[Test]
    public function valuesSentInFieldsOfAnotherKindAreEmptiedAndReportedAsInformation(): void
    {
        $this->processDatamap(['be_groups' => [2 => ['subgroup' => '3', 'file_permissions' => 'readFolder']]]);

        self::assertSame(['subgroup' => '', 'file_permissions' => ''], $this->getFields(2, ['subgroup', 'file_permissions']));
        self::assertSame([[0, '{record}: Fields not belonging to kind "{kind}" have been emptied: {fields}']], $this->getLogEntries());
    }

    #[Test]
    public function rejectedRelationsAreReportedAsUserErrors(): void
    {
        $this->processDatamap(['be_groups' => [1 => ['subgroup' => '2,3']]]);

        self::assertSame(['subgroup' => '2'], $this->getFields(1, ['subgroup']));
        self::assertSame([[1, '{record}: Only building blocks can be added to a role. Rejected groups: {groups}']], $this->getLogEntries());
    }

    /**
     * @param array<string, array<int, array<string, string>>> $datamap
     */
    private function processDatamap(array $datamap): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start($datamap, []);
        $dataHandler->process_datamap();
    }

    /**
     * @param list<string> $fields
     * @return array<string, mixed>
     */
    private function getFields(int $uid, array $fields): array
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable('be_groups');
        $record = $queryBuilder->select(...$fields)->from('be_groups')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();
        self::assertIsArray($record);
        return $record;
    }

    /**
     * @return list<array{int, string}> the level ("error") and the message of the entries of extensions
     */
    private function getLogEntries(): array
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable('sys_log');
        $entries = [];
        foreach ($queryBuilder->select('error', 'details')->from('sys_log')
            ->where($queryBuilder->expr()->eq('type', $queryBuilder->createNamedParameter(4, Connection::PARAM_INT)))
            ->orderBy('uid')
            ->executeQuery()
            ->fetchAllAssociative() as $entry) {
            $entries[] = [is_numeric($entry['error']) ? (int)$entry['error'] : -1, is_string($entry['details']) ? $entry['details'] : ''];
        }
        return $entries;
    }
}
