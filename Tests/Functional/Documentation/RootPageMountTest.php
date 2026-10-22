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

use PHPUnit\Framework\Attributes\CoversNothing;
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
 * The backend form of TYPO3 does not show the root of the page tree ("0") as page mount, so
 * saving the group in the form removes it, as the manual warns (Migration, "Afterwards").
 */
#[CoversNothing]
final class RootPageMountTest extends FunctionalTestCase
{
    /**
     * Value of SystemEnvironmentBuilder::REQUESTTYPE_BE, which is @internal.
     */
    private const BACKEND_REQUEST_TYPE = 2;

    protected array $testExtensionsToLoad = ['cretection/be-groups'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/RootPageMount.csv');
    }

    #[Test]
    public function theFormDoesNotShowTheRootAsPageMountAndSavingItRemovesTheRoot(): void
    {
        self::assertCount(0, $this->getPageMountsOfTheForm(1), 'the root');
        self::assertCount(1, $this->getPageMountsOfTheForm(2), 'a page');

        // The form sends the page mounts it shows
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start(['be_groups' => [1 => ['db_mountpoints' => '']]], []);
        $dataHandler->process_datamap();
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable('be_groups');
        $pageMounts = $queryBuilder->select('db_mountpoints')->from('be_groups')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter(1, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();
        self::assertSame('', $pageMounts);
    }

    /**
     * @return array<array-key, mixed> the page mounts the form of a group shows
     */
    private function getPageMountsOfTheForm(int $uid): array
    {
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
        $request = $this->get(ServerRequestFactoryInterface::class)
            ->createServerRequest('GET', 'https://example.org/typo3/record/edit')
            ->withAttribute('applicationType', self::BACKEND_REQUEST_TYPE);
        $GLOBALS['TYPO3_REQUEST'] = $request;
        $formData = $this->get(FormDataCompiler::class)->compile(
            ['request' => $request, 'tableName' => 'be_groups', 'vanillaUid' => $uid, 'command' => 'edit'],
            $this->get(TcaDatabaseRecord::class),
        );

        $databaseRow = $formData['databaseRow'] ?? null;
        self::assertIsArray($databaseRow);
        $pageMounts = $databaseRow['db_mountpoints'] ?? null;
        self::assertIsArray($pageMounts);
        return $pageMounts;
    }
}
