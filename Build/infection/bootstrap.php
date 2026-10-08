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

// Bootstrap of the mutation tests: the unit and the functional tests run in one PHPUnit process.
// The functional bootstrap only defines the root path and creates the test directories, the unit
// bootstrap prepares the environment the unit tests expect. Every functional test builds its own
// instance anyway.

require __DIR__ . '/../../.Build/vendor/typo3/testing-framework/Resources/Core/Build/FunctionalTestsBootstrap.php';
require __DIR__ . '/../../.Build/vendor/typo3/testing-framework/Resources/Core/Build/UnitTestsBootstrap.php';
