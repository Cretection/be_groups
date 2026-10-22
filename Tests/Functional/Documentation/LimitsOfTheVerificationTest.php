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

use Cretection\BeGroups\Domain\Classification\ConversionStatus;
use Cretection\BeGroups\Domain\Classification\GroupConverter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * The verification of the assistants compares groups and users, as the manual says (Migration,
 * "Limits of the verification"): what another extension changes in other tables while a group is
 * converted is committed with the conversion, here a new building block as owner group of a page.
 */
#[CoversClass(GroupConverter::class)]
final class LimitsOfTheVerificationTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'cretection/be-groups',
        __DIR__ . '/../Fixtures/Extensions/be_groups_test_page_owner',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/LimitsOfTheVerification.csv');
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
    }

    #[Test]
    public function changesOfOtherExtensionsOutsideGroupsAndUsersAreCommitted(): void
    {
        $result = $this->get(GroupConverter::class)->split(1);

        self::assertSame(ConversionStatus::Converted, $result->status, $result->message);
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable('pages');
        $ownerGroup = $queryBuilder->select('perms_groupid')->from('pages')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter(1, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();
        self::assertContains(is_numeric($ownerGroup) ? (int)$ownerGroup : null, $result->createdBlockUids);
    }
}
