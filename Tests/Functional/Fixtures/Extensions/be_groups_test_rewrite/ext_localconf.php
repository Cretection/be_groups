<?php

declare(strict_types=1);

use Cretection\BeGroups\Tests\Functional\Fixtures\Extensions\be_groups_test_rewrite\Classes\AddPageMount;

defined('TYPO3') or die();

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['be_groups_test_rewrite'] = AddPageMount::class;
