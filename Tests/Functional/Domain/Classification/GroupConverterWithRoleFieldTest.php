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

namespace Cretection\BeGroups\Tests\Functional\Domain\Classification;

use Cretection\BeGroups\Domain\Classification\ConversionStatus;
use Cretection\BeGroups\Domain\Classification\GroupClassifier;
use Cretection\BeGroups\Domain\Classification\GroupConverter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Another extension shows its permission field in the forms of roles and of access rights
 * (see be_groups_test_role_field). A split moves it into a building block like every other field.
 */
#[CoversClass(GroupConverter::class)]
#[CoversClass(GroupClassifier::class)]
final class GroupConverterWithRoleFieldTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'cretection/be-groups',
        __DIR__ . '/../../Fixtures/Extensions/be_groups_test_role_field',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/ClassicGroups.csv');
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
    }

    #[Test]
    public function aSplitMovesFieldsTheFormOfARoleShowsAsWell(): void
    {
        $this->getConnectionPool()->getConnectionForTable('be_groups')->update('be_groups', ['tx_test_role_permission' => 'granted'], ['uid' => 2]);

        $result = $this->get(GroupConverter::class)->split(2);

        self::assertSame(ConversionStatus::Converted, $result->status, $result->message);
        [, $aclUid] = $result->createdBlockUids;
        self::assertSame(['tx_begroups_kind' => 'acl', 'groupMods' => 'web_layout', 'tx_test_role_permission' => 'granted'], $this->getGroup($aclUid, ['tx_begroups_kind', 'groupMods', 'tx_test_role_permission']));
        // Otherwise the role would still grant it after the building block is removed from it.
        self::assertSame(['tx_begroups_kind' => 'role', 'tx_test_role_permission' => ''], $this->getGroup(2, ['tx_begroups_kind', 'tx_test_role_permission']));
    }

    /**
     * @param list<string> $fields
     * @return array<string, string>
     */
    private function getGroup(int $uid, array $fields): array
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('be_groups');
        $queryBuilder->getRestrictions()->removeAll();
        $row = $queryBuilder
            ->select(...$fields)
            ->from('be_groups')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();
        self::assertIsArray($row);
        return array_map(static fn(mixed $value): string => is_scalar($value) ? (string)$value : '', $row);
    }
}
