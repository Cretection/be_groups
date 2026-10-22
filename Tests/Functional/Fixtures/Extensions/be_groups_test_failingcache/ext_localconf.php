<?php

declare(strict_types=1);

use Cretection\BeGroups\Tests\Functional\Fixtures\Extensions\be_groups_test_failingcache\Classes\FailingCacheFlush;

defined('TYPO3') or die();

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['clearCachePostProc']['be_groups_test_failingcache']
    = FailingCacheFlush::class . '->failAfterCacheCommand';
