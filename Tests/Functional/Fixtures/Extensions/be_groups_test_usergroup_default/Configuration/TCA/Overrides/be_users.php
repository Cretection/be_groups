<?php

declare(strict_types=1);

defined('TYPO3') or die();

// New users get the role "R editor" of the fixture GroupsAndUsers.csv
$GLOBALS['TCA']['be_users']['columns']['usergroup']['config']['default'] = '6';
