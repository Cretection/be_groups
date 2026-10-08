#!/usr/bin/env bash

#
# Sets up the TYPO3 instance for the end-to-end tests (CODING_GUIDELINES.md, §13) in ".Build/e2e":
# a composer project with TYPO3 14.3, be_groups linked from this repository, SQLite and the
# roles, building blocks and users of "Build/tests/playwright/scenario.php".
# Called by "Build/Scripts/runTests.sh -s e2e" inside the PHP container, after the pattern of
# "Build/Scripts/setupAcceptanceComposer.sh" of the TYPO3 Core.
#

set -e

cd "$(dirname "$(realpath "$0")")/../../"
ROOT_DIR="$(pwd)"
PROJECT_PATH="${ROOT_DIR}/.Build/e2e"

rm -rf "${PROJECT_PATH}"
mkdir -p "${PROJECT_PATH}/config/system"

cat > "${PROJECT_PATH}/composer.json" <<\EOF
{
    "name": "cretection/be-groups-e2e",
    "description": "Instance for the end-to-end tests of be_groups",
    "license": "GPL-2.0-or-later",
    "type": "project",
    "require": {
        "cretection/be-groups": "@dev",
        "typo3/cms-backend": "^14.3",
        "typo3/cms-belog": "^14.3",
        "typo3/cms-beuser": "^14.3",
        "typo3/cms-core": "^14.3",
        "typo3/cms-frontend": "^14.3",
        "typo3/cms-install": "^14.3",
        "typo3/cms-workspaces": "^14.3"
    },
    "repositories": [
        {
            "type": "path",
            "url": "../..",
            "options": {
                "symlink": true
            }
        }
    ],
    "config": {
        "allow-plugins": {
            "typo3/class-alias-loader": true,
            "typo3/cms-composer-installers": true
        },
        "lock": false
    },
    "extra": {
        "typo3/cms": {
            "web-dir": "public"
        }
    }
}
EOF

cat > "${PROJECT_PATH}/config/system/additional.php" <<\EOF
<?php

$GLOBALS['TYPO3_CONF_VARS']['BE']['debug'] = true;
$GLOBALS['TYPO3_CONF_VARS']['SYS']['displayErrors'] = true;
$GLOBALS['TYPO3_CONF_VARS']['SYS']['devIPmask'] = '*';
$GLOBALS['TYPO3_CONF_VARS']['SYS']['exceptionalErrors'] = E_ALL;
$GLOBALS['TYPO3_CONF_VARS']['SYS']['errorHandlerErrors'] = E_ALL;
$GLOBALS['TYPO3_CONF_VARS']['SYS']['trustedHostsPattern'] = '.*';
$GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport'] = 'mbox';
$GLOBALS['TYPO3_CONF_VARS']['MAIL']['transport_mbox_file'] = \TYPO3\CMS\Core\Core\Environment::getVarPath() . '/log/mail.mbox';
// The web server runs several PHP processes on one SQLite file, as in the Core
if (($GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['driver'] ?? '') === 'pdo_sqlite' || getenv('TYPO3_DB_DRIVER') === 'sqlite') {
    $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['driverOptions'] = [
        \PDO::ATTR_TIMEOUT => 120,
    ];
    $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections']['Default']['initCommands'] =
        'PRAGMA journal_mode = WAL;' . LF .
        'PRAGMA busy_timeout = 120000;' . LF .
        'PRAGMA synchronous = NORMAL;';
}
EOF

cd "${PROJECT_PATH}"
composer install --no-progress --no-interaction --optimize-autoloader

TYPO3_DB_DRIVER=sqlite \
TYPO3_SETUP_ADMIN_USERNAME="${E2E_ADMIN_USERNAME:-admin}" \
TYPO3_SETUP_ADMIN_PASSWORD="${E2E_ADMIN_PASSWORD:-E2e-Password-1}" \
TYPO3_SETUP_ADMIN_EMAIL="admin@example.org" \
TYPO3_PROJECT_NAME="be_groups end-to-end tests" \
TYPO3_SERVER_TYPE=apache \
vendor/bin/typo3 setup --force --no-interaction

vendor/bin/typo3 extension:setup
php "${ROOT_DIR}/Build/tests/playwright/scenario.php" "${PROJECT_PATH}"
vendor/bin/typo3 cache:flush
