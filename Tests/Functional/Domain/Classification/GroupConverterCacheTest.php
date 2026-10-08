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

use Cretection\BeGroups\DataHandling\CacheCommandDeferral;
use Cretection\BeGroups\Domain\Classification\ConversionStatus;
use Cretection\BeGroups\Domain\Classification\GroupConverter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * Many site packages flush the page caches after every save (TCEMAIN.clearCacheCmd = pages).
 * The database cache backend truncates its tables then, which commits an open transaction
 * implicitly on MySQL and MariaDB. Conversions must stay atomic nevertheless.
 */
#[CoversClass(GroupConverter::class)]
#[CoversClass(CacheCommandDeferral::class)]
final class GroupConverterCacheTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'cretection/be-groups',
        __DIR__ . '/../../Fixtures/Extensions/be_groups_test_rewrite',
        __DIR__ . '/../../Fixtures/Extensions/be_groups_test_clearcache',
    ];

    /**
     * The testing framework uses a null backend for the page caches.
     */
    protected array $configurationToUseInTestInstance = [
        'SYS' => [
            'caching' => [
                'cacheConfigurations' => [
                    'pages' => ['backend' => Typo3DatabaseBackend::class],
                ],
            ],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/ClassicGroups.csv');
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
    }

    #[Test]
    public function aFailedConversionChangesNothingAlthoughThePageCachesAreFlushedAfterSaving(): void
    {
        $this->getConnectionPool()->getConnectionForTable('be_groups')
            ->update('be_groups', ['description' => 'rewrite:widen-other-group'], ['uid' => 6]);
        $this->get(CacheManager::class)->getCache('pages')->set('begroups_test', 'cached');
        $groupCount = $this->countGroups();

        $result = $this->get(GroupConverter::class)->split(6);

        self::assertSame(ConversionStatus::Failed, $result->status);
        self::assertSame($groupCount, $this->countGroups());
        self::assertSame('classic', $this->getKind(6));
        self::assertTrue($this->get(CacheManager::class)->getCache('pages')->has('begroups_test'));
    }

    #[Test]
    public function thePageCachesAreFlushedAfterASuccessfulConversion(): void
    {
        $this->get(CacheManager::class)->getCache('pages')->set('begroups_test', 'cached');

        $result = $this->get(GroupConverter::class)->split(6);

        self::assertSame(ConversionStatus::Converted, $result->status);
        self::assertSame('role', $this->getKind(6));
        self::assertFalse($this->get(CacheManager::class)->getCache('pages')->has('begroups_test'));
    }

    private function countGroups(): int
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('be_groups');
        $queryBuilder->getRestrictions()->removeAll();
        $count = $queryBuilder->count('uid')->from('be_groups')->executeQuery()->fetchOne();
        return is_numeric($count) ? (int)$count : 0;
    }

    private function getKind(int $uid): string
    {
        $kind = $this->getConnectionPool()->getConnectionForTable('be_groups')
            ->select(['tx_begroups_kind'], 'be_groups', ['uid' => $uid])
            ->fetchOne();
        return is_string($kind) ? $kind : '';
    }
}
