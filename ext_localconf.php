<?php

declare(strict_types=1);

use Cretection\BeGroups\DataHandling\GroupKindRules;

defined('TYPO3') or die();

// Enforces the rules of the group kind model on every DataHandler write (forms, imports, API).
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass']['be_groups']
    = GroupKindRules::class;
