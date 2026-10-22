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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestFactoryInterface;
use TYPO3\CMS\Backend\Form\FormDataCompiler;
use TYPO3\CMS\Backend\Form\FormDataGroup\TcaDatabaseRecord;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Defaults of new groups and users are set as the manual shows (Concept, "Rules" and "Known
 * limitations"): groups and users are stored on the root level, so TCAdefaults in the global
 * page TSconfig of a site package apply to them, and the TSconfig of a page does not.
 */
#[CoversClass(GroupKindRules::class)]
final class DefaultsOfNewRecordsTest extends FunctionalTestCase
{
    /**
     * Value of SystemEnvironmentBuilder::REQUESTTYPE_BE, which is @internal.
     */
    private const BACKEND_REQUEST_TYPE = 2;

    protected array $testExtensionsToLoad = [
        'cretection/be-groups',
        __DIR__ . '/../Fixtures/Extensions/be_groups_test_sitepackage',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DefaultsOfNewRecords.csv');
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
    }

    #[Test]
    public function newUsersGetNoFileOperations(): void
    {
        $uid = $this->create('be_users', ['username' => 'anna', 'password' => 'Anna-Password-1!', 'usergroup' => '1']);

        self::assertSame('', $this->getField('be_users', $uid, 'file_permissions'));
    }

    #[Test]
    public function theFormOfANewUserStartsWithoutFileOperations(): void
    {
        $request = $this->get(ServerRequestFactoryInterface::class)
            ->createServerRequest('GET', 'https://example.org/typo3/record/edit')
            ->withAttribute('applicationType', self::BACKEND_REQUEST_TYPE);
        $GLOBALS['TYPO3_REQUEST'] = $request;
        $formData = $this->get(FormDataCompiler::class)->compile(
            ['request' => $request, 'tableName' => 'be_users', 'vanillaUid' => 0, 'command' => 'new'],
            $this->get(TcaDatabaseRecord::class),
        );

        $databaseRow = $formData['databaseRow'] ?? null;
        self::assertIsArray($databaseRow);
        self::assertSame([], $databaseRow['file_permissions'] ?? null);
    }

    #[Test]
    public function theKindOfNewGroupsComesFromTheGlobalPageTsConfig(): void
    {
        // The user TSconfig sets "acl", the TSconfig of the root page "language": neither applies.
        $uid = $this->create('be_groups', ['title' => 'Website']);

        self::assertSame('db_mount', $this->getField('be_groups', $uid, 'tx_begroups_kind'));
    }

    /**
     * @param array<string, string> $fields
     */
    private function create(string $table, array $fields): int
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([$table => ['NEW1' => ['pid' => 0] + $fields]], []);
        $dataHandler->process_datamap();
        $uid = $dataHandler->substNEWwithIDs['NEW1'] ?? null;
        self::assertIsInt($uid);
        return $uid;
    }

    private function getField(string $table, int $uid, string $field): mixed
    {
        // New backend users are disabled by default.
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        $record = $queryBuilder->select($field)->from($table)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();
        self::assertIsArray($record);
        return $record[$field] ?? null;
    }
}
