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
use Cretection\BeGroups\Domain\Classification\GroupConverter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * The cache commands of page TSconfig run after the commit of a conversion (see GroupConverterCacheTest).
 * If they fail, e.g. because the server of a cache is unreachable, the conversion is done all the same.
 */
#[CoversClass(GroupConverter::class)]
final class GroupConverterFailingCacheTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'cretection/be-groups',
        __DIR__ . '/../../Fixtures/Extensions/be_groups_test_clearcache',
        __DIR__ . '/../../Fixtures/Extensions/be_groups_test_failingcache',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/ClassicGroups.csv');
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
    }

    #[Test]
    public function reportsACommittedSplitAlthoughTheCacheCommandsFail(): void
    {
        $result = $this->get(GroupConverter::class)->split(6);

        self::assertSame(ConversionStatus::Converted, $result->status, $result->message);
        self::assertSame(
            'The group is now a role with 2 new building block(s). Then the cache commands of page TSconfig (TCEMAIN.clearCacheCmd) failed, so clear the caches yourself: The cache server is unreachable.',
            $result->message,
        );
        self::assertCount(2, $result->createdBlockUids);
        self::assertSame('role', $this->getKind(6));
    }

    #[Test]
    public function reportsACommittedKindChangeAlthoughTheCacheCommandsFail(): void
    {
        $result = $this->get(GroupConverter::class)->changeKind(1);

        self::assertSame(ConversionStatus::Converted, $result->status, $result->message);
        self::assertStringStartsWith('The kind is now "db_mount". Then the cache commands of page TSconfig', $result->message);
        self::assertSame('db_mount', $this->getKind(1));
    }

    private function getKind(int $uid): string
    {
        $kind = $this->getConnectionPool()->getConnectionForTable('be_groups')
            ->select(['tx_begroups_kind'], 'be_groups', ['uid' => $uid])
            ->fetchOne();
        return is_string($kind) ? $kind : '';
    }
}
