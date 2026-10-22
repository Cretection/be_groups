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

namespace Cretection\BeGroups\Tests\Functional\Fixtures\Extensions\be_groups_test_failingcache\Classes;

/**
 * Simulates a cache that cannot be flushed, e.g. because its server is unreachable: every cache
 * command fails once it has run (DataHandler hook "clearCachePostProc").
 */
final class FailingCacheFlush
{
    /**
     * @param array<string, mixed> $parameters "cacheCmd" is set after a cache command
     */
    public function failAfterCacheCommand(array $parameters): void
    {
        if (isset($parameters['cacheCmd'])) {
            throw new \RuntimeException('The cache server is unreachable.', 1792703337);
        }
    }
}
