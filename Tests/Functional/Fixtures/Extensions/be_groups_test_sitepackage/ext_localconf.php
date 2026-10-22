<?php

declare(strict_types=1);

defined('TYPO3') or die();

// The override of the prefixes exactly as the manual shows it (Usage, "The kind as prefix")
$GLOBALS['TYPO3_CONF_VARS']['LANG']['resourceOverrides']['EXT:be_groups/Resources/Private/Language/db.xlf'][]
    = 'EXT:be_groups_test_sitepackage/Resources/Private/Language/Overrides/be_groups.db.xlf';
