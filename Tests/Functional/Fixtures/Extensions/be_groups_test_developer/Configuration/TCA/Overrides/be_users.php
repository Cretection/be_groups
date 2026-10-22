<?php

declare(strict_types=1);

defined('TYPO3') or die();

// The example of the chapter Developer as the manual shows it (ManualTest compares it)

$GLOBALS['TCA']['be_users']['columns']['usergroup']['config']['itemGroups']['my_news']
    = 'be_groups_test_developer.db:be_users.usergroup.group.news';
