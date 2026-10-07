#!/usr/bin/env bash
# shellcheck disable=SC2086,SC2128,SC2178,SC2206

#
# Test runner of the TYPO3 extension "be_groups", based on containers (docker or podman).
#
# Adapted from the runTests.sh of the TYPO3 best practice extension "tea"
# (https://github.com/TYPO3BestPractices/tea) and the TYPO3 Core. Image tags are
# pinned like in the TYPO3 Core 14.3, see CODING_GUIDELINES.md, §14.
#

# Uncomment for debugging
# set -x

if [ "${CI}" != "true" ]; then
    trap 'echo "runTests.sh SIGINT signal emitted";cleanUp;exit 2' SIGINT
fi

printSummary() {
    cleanUp

    echo "" >&2
    echo "###########################################################################" >&2
    echo "Result of ${TEST_SUITE}" >&2
    echo "Container runtime: ${CONTAINER_BIN}" >&2
    echo "Container suffix: ${SUFFIX}"
    echo "PHP: ${PHP_VERSION}" >&2
    echo "TYPO3: ${CORE_VERSION}" >&2
    if [[ ${TEST_SUITE} =~ ^functional$ ]]; then
        case "${DBMS}" in
            mariadb|mysql|postgres)
                echo "DBMS: ${DBMS}  version ${DBMS_VERSION}  driver ${DATABASE_DRIVER}" >&2
                ;;
            sqlite)
                echo "DBMS: ${DBMS}" >&2
                ;;
        esac
    fi
    if [ ${CREATE_COVERAGE} -eq 1 ] && [ -n "${COVERAGE_FILE}" ] && [ -f "${ROOT_DIR}/.Build/coverage/${COVERAGE_FILE}" ]; then
        echo "COVERAGE-FILE: .Build/coverage/${COVERAGE_FILE}" >&2
    fi
    if [[ ${SUITE_EXIT_CODE} -eq 0 ]]; then
        echo "SUCCESS" >&2
    else
        echo "FAILURE" >&2
    fi
    echo "###########################################################################" >&2
    echo "" >&2
    exit ${SUITE_EXIT_CODE}
}

waitFor() {
    local HOST=${1}
    local PORT=${2}
    # 60 rather than 20 seconds: databases need noticeably longer under docker than under
    # podman to initialise a fresh data directory, and CI selects docker.
    local TESTCOMMAND="
        COUNT=0;
        while ! nc -z ${HOST} ${PORT}; do
            if [ \"\${COUNT}\" -gt 60 ]; then
              echo \"Can not connect to ${HOST} port ${PORT}. Aborting.\";
              exit 1;
            fi;
            sleep 1;
            COUNT=\$((COUNT + 1));
        done;
    "
    if ! ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name wait-for-${SUFFIX} ${XDEBUG_MODE} -e XDEBUG_CONFIG="${XDEBUG_CONFIG}" ${IMAGE_PHP} /bin/sh -c "${TESTCOMMAND}"; then
        echo "runTests.sh SIGINT signal emitted"
        cleanUp
        exit 2
    fi
}

cleanUp() {
    echo "Remove container for network \"${NETWORK}\""
    ATTACHED_CONTAINERS=$(${CONTAINER_BIN} ps --filter network=${NETWORK} --format='{{.Names}}')
    for ATTACHED_CONTAINER in ${ATTACHED_CONTAINERS}; do
        ${CONTAINER_BIN} kill ${ATTACHED_CONTAINER} >/dev/null
    done
    if [ ${CONTAINER_BIN} = "docker" ]; then
        ${CONTAINER_BIN} network rm ${NETWORK} >/dev/null
    else
        ${CONTAINER_BIN} network rm -f ${NETWORK} >/dev/null
    fi
}

invalidCombination() {
    echo "Invalid combination ${1}" >&2
    echo >&2
    echo "Use \"Build/Scripts/runTests.sh -h\" to display help and valid options" >&2
    exit 1
}

handleDbmsOptions() {
    # -a, -d, -i depend on each other. Validate input combinations and set defaults.
    case ${DBMS} in
        mariadb)
            [ -z "${DATABASE_DRIVER}" ] && DATABASE_DRIVER="mysqli"
            if [ "${DATABASE_DRIVER}" != "mysqli" ] && [ "${DATABASE_DRIVER}" != "pdo_mysql" ]; then
                invalidCombination "-d ${DBMS} -a ${DATABASE_DRIVER}"
            fi
            [ -z "${DBMS_VERSION}" ] && DBMS_VERSION="10.11"
            if ! [[ ${DBMS_VERSION} =~ ^(10.6|10.11|11.4|11.8)$ ]]; then
                invalidCombination "-d ${DBMS} -i ${DBMS_VERSION}"
            fi
            ;;
        mysql)
            [ -z "${DATABASE_DRIVER}" ] && DATABASE_DRIVER="mysqli"
            if [ "${DATABASE_DRIVER}" != "mysqli" ] && [ "${DATABASE_DRIVER}" != "pdo_mysql" ]; then
                invalidCombination "-d ${DBMS} -a ${DATABASE_DRIVER}"
            fi
            [ -z "${DBMS_VERSION}" ] && DBMS_VERSION="8.0"
            if ! [[ ${DBMS_VERSION} =~ ^(8.0|8.4)$ ]]; then
                invalidCombination "-d ${DBMS} -i ${DBMS_VERSION}"
            fi
            ;;
        postgres)
            if [ -n "${DATABASE_DRIVER}" ]; then
                invalidCombination "-d ${DBMS} -a ${DATABASE_DRIVER}"
            fi
            [ -z "${DBMS_VERSION}" ] && DBMS_VERSION="14"
            if ! [[ ${DBMS_VERSION} =~ ^(14|15|16|17|18)$ ]]; then
                invalidCombination "-d ${DBMS} -i ${DBMS_VERSION}"
            fi
            ;;
        sqlite)
            if [ -n "${DATABASE_DRIVER}" ]; then
                invalidCombination "-d ${DBMS} -a ${DATABASE_DRIVER}"
            fi
            if [ -n "${DBMS_VERSION}" ]; then
                invalidCombination "-d ${DBMS} -i ${DBMS_VERSION}"
            fi
            ;;
        *)
            echo "Invalid option -d ${DBMS}" >&2
            echo >&2
            echo "Use \"Build/Scripts/runTests.sh -h\" to display help and valid options" >&2
            exit 1
            ;;
    esac
}

getPhpImageVersion() {
    # Pinned like in the TYPO3 Core 14.3 (Build/Scripts/runTests.sh)
    case ${1} in
        8.2)
            echo -n "1.15"
            ;;
        8.3)
            echo -n "1.16"
            ;;
        8.4)
            echo -n "1.8"
            ;;
        8.5)
            echo -n "1.8"
            ;;
    esac
}

cleanCacheFiles() {
    echo -n "Clean caches ... "
    rm -rf \
        .Build/.cache \
        .cache \
        .php-cs-fixer.cache
    echo "done"
}

cleanTestFiles() {
    echo -n "Clean test related files ... "
    rm -rf \
        .Build/public/typo3temp/var/tests/ \
        .Build/coverage/ \
        .Build/logs/
    echo "done"
}

cleanRenderedDocumentationFiles() {
    echo -n "Clean rendered documentation files ... "
    rm -rf \
        Documentation-GENERATED-temp
    echo "done"
}

prepareCoverage() {
    # Fills "${COVERAGE_OPTION}" with the PHPUnit option collecting the coverage, based on the
    # file name the calling suite has put into "${COVERAGE_FILE}". Without "-m", the array stays
    # empty, and an empty array adds no argument at all to the PHPUnit call.
    COVERAGE_OPTION=()
    if [ ${CREATE_COVERAGE} -eq 0 ]; then
        return
    fi
    mkdir -p "${ROOT_DIR}/.Build/coverage" "${ROOT_DIR}/.Build/logs"
    COVERAGE_OPTION=("--coverage-php=.Build/coverage/${COVERAGE_FILE}")
}

loadHelp() {
    # Load help text into $HELP
    read -r -d '' HELP <<EOF
Test runner of the TYPO3 extension "be_groups". Executes unit, functional and other
test suites in a container based test environment (docker or podman).

Usage: $0 [options] [file]

Options:
    -s <...>
        Specifies the test suite to run
            - cgl: Fixes the code style and the license headers with PHP-CS-Fixer
              (typo3/coding-standards). Set -n for dry-run.
            - clean: clean up build, cache and testing related files and folders
            - cleanCache: clean up cache related files and folders
            - cleanDocs: clean up rendered documentation files and folders (Documentation-GENERATED-temp)
            - cleanTests: clean up test related files and folders
            - composer: "composer" with all remaining arguments dispatched.
            - composerNormalize: Normalizes the composer.json. Set -n for dry-run.
            - composerUpdateMax: "composer update" with the highest dependencies. Works on a
              throwaway copy of the manifest, so "composer.json" stays untouched.
            - composerUpdateDev: install the development version of the next TYPO3 major version
              (typo3/cms-*:dev-main, typo3/testing-framework:dev-main, PHPUnit 12) to check the
              forward compatibility. Works on a throwaway copy of the manifest. Use with -p 8.5.
            - composerUpdateMin: "composer update --prefer-lowest", with platform.php set to the
              selected PHP version x.x.0. "composer.json" stays untouched, see composerUpdateMax.
            - coverageMerge: Merges the coverage collected with -m into one clover report
              (needs "phpunit/phpcov").
            - docs: Renders the documentation and fails on rendering warnings and errors.
            - fix: Runs all automatic fixes (composer normalize, Rector, PHP-CS-Fixer, XLIFF).
              All steps run; the suite fails if any of them fails.
            - functional: PHP functional tests
            - integrity: PHP integrity checks (exception codes, final test classes,
              no PHPUnit annotations, no "test" method prefix)
            - lint: Runs all linters (PHP, JSON, YAML)
            - lintJson: JSON linting
            - lintPhp: PHP linting
            - lintYaml: YAML linting
            - phpstan: PHPStan (level max, without baseline)
            - psrVerify: Verifies PSR-4 namespace correctness.
            - rector: Fixes and upgrades the PHP code using Rector. Set -n for dry-run.
            - shellcheck: check runTests.sh for shell issues
            - unit (default): PHP unit tests
            - unitRandom: PHP unit tests in random order, add -o <number> to use specific seed
            - update: Updates existing typo3/core-testing-* container images and removes dangling local volumes.
            - xliffLint: Checks the integrity of all XLIFF files.
            - xliffNormalize: Normalizes the formatting of all XLIFF files. Set -n for dry-run.

    -b <docker|podman>
        Container environment:
            - podman (default, if installed)
            - docker
        Use "-b docker" in CI, podman is not reliable on GitHub runners.

    -a <mysqli|pdo_mysql>
        Only with -s functional
        Specifies to use another driver, following combinations are available:
            - mysql
                - mysqli (default)
                - pdo_mysql
            - mariadb
                - mysqli (default)
                - pdo_mysql

    -d <sqlite|mariadb|mysql|postgres>
        Only with -s functional
        Specifies on which DBMS tests are performed
            - sqlite: (default): use sqlite
            - mariadb: use mariadb
            - mysql: use MySQL
            - postgres: use postgres

    -i version
        Specify a specific database version
        With "-d mariadb":
            - 10.6   long-term, maintained until 2026-07
            - 10.11  long-term, maintained until 2028-02 (default)
            - 11.4   long-term, maintained until 2029-05
            - 11.8   long-term, maintained until 2030-06
        With "-d mysql":
            - 8.0   (default)
            - 8.4   LTS, maintained until 2032-04
        With "-d postgres":
            - 14    maintained until 2026-11-12 (default)
            - 15    maintained until 2027-11-11
            - 16    maintained until 2028-11-09
            - 17    maintained until 2029-11-08
            - 18    maintained until 2030-11-14

    -p <8.2|8.3|8.4|8.5>
        Specifies the PHP minor version to be used
            - 8.2: use PHP 8.2
            - 8.3: use PHP 8.3
            - 8.4: use PHP 8.4
            - 8.5 (default): use PHP 8.5

    -x
        Only with -s functional|unit|unitRandom
        Send information to host instance for test or system under test break points. This is especially
        useful if a local PhpStorm instance is listening on default xdebug port 9003. A different port
        can be selected with -y

    -y <port>
        Send xdebug information to a different port than default 9003 if an IDE like PhpStorm
        is not listening on default port.

    -o <number>
        Only with -s unitRandom
        Set specific random seed to replay a random run in this order again. The phpunit randomizer
        outputs the used seed at the end. Use that number to replay the unit tests in that order.

    -n
        Only with -s cgl|composerNormalize|rector|xliffNormalize
        Activate dry-run: do not modify files, only report issues.

    -m
        Only with -s functional|unit|unitRandom
        Collect code coverage while the tests run. The report is written to
        ".Build/coverage/", under a name unique for the PHP version and the DBMS, so that
        the runs of a test matrix do not overwrite each other. Merge all collected reports
        into ".Build/logs/clover.xml" with "-s coverageMerge" afterwards.
        Cannot be combined with -x, as both need a different Xdebug mode.

    -u
        Update existing typo3/core-testing-* container images and remove obsolete dangling image versions.
        Also removes dangling local volumes. Use this if weird test errors occur.

    -h
        Show this help.

Examples:
    # Run all unit tests using PHP 8.5
    ./Build/Scripts/runTests.sh
    ./Build/Scripts/runTests.sh -s unit

    # Run all unit tests and enable xdebug (have a PhpStorm listening on port 9003!)
    ./Build/Scripts/runTests.sh -x

    # Run functional tests in phpunit with a filtered test method name in a specified file
    ./Build/Scripts/runTests.sh -s functional -- --filter aTestName path/to/fileTest.php

    # Run functional tests on postgres 18 with PHP 8.2
    ./Build/Scripts/runTests.sh -s functional -p 8.2 -d postgres -i 18

    # Check the code style without changing files
    ./Build/Scripts/runTests.sh -s cgl -n

    # Some composer command examples
    ./Build/Scripts/runTests.sh -s composer -- install
    ./Build/Scripts/runTests.sh -s composer -- dumpautoload

    # Collect the coverage of the unit and the functional tests and merge both into one report
    ./Build/Scripts/runTests.sh -p 8.2 -s unit -m
    ./Build/Scripts/runTests.sh -p 8.2 -s functional -m
    ./Build/Scripts/runTests.sh -p 8.2 -s coverageMerge
EOF
}

# Functions for the individual checkers/fixers

cgl() {
    # Active dry-run for cgl needs not "-n" but specific options
    local CGL_OPTIONS=""
    if [ -n "${CGLCHECK_DRY_RUN}" ]; then
        CGL_OPTIONS="--dry-run --diff"
    fi
    # The code style (Core rule set) and the license headers are checked with two configurations,
    # like in the TYPO3 Core, as configuration files do not carry a license header.
    COMMAND="php -dxdebug.mode=off .Build/bin/php-cs-fixer fix -v ${CGL_OPTIONS} --config=Build/php-cs-fixer/config.php \
        && php -dxdebug.mode=off .Build/bin/php-cs-fixer fix -v ${CGL_OPTIONS} --config=Build/php-cs-fixer/header-comment.php"
    ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name cgl-${SUFFIX} ${IMAGE_PHP} /bin/sh -c "${COMMAND}"
}

composerNormalize() {
    local NORMALIZE_OPTIONS=""
    if [ -n "${CGLCHECK_DRY_RUN}" ]; then
        NORMALIZE_OPTIONS="--dry-run"
    fi
    COMMAND="composer normalize --no-check-lock ${NORMALIZE_OPTIONS}"
    ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name composer-normalize-${SUFFIX} ${COMPOSER_PARAMS} ${IMAGE_PHP} /bin/sh -c "${COMMAND}"
}

integrity() {
    ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name integrity-${SUFFIX} ${IMAGE_PHP} php -dxdebug.mode=off Build/Scripts/phpIntegrityChecker.php
}

lintJson() {
    ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name lint-json-${SUFFIX} ${COMPOSER_PARAMS} ${IMAGE_PHP} /bin/sh -c "composer check:json:lint"
}

lintPhp() {
    ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name lint-php-${SUFFIX} ${COMPOSER_PARAMS} ${IMAGE_PHP} /bin/sh -c "composer check:php:lint"
}

lintYaml() {
    ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name lint-yaml-${SUFFIX} ${COMPOSER_PARAMS} ${IMAGE_PHP} /bin/sh -c "composer check:yaml:lint"
}

phpstan() {
    COMMAND=(php -dxdebug.mode=off .Build/bin/phpstan analyse -c Build/phpstan/phpstan.neon --no-progress --no-interaction --memory-limit 4G "$@")
    ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name phpstan-${SUFFIX} ${IMAGE_PHP} "${COMMAND[@]}"
}

psrVerify() {
    COMMAND="composer dumpautoload --optimize --strict-psr --no-plugins --dry-run"
    ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name psr-verify-${SUFFIX} ${COMPOSER_PARAMS} ${IMAGE_PHP} /bin/sh -c "${COMMAND}"
}

rector() {
    local RECTOR_OPTIONS=""
    if [ -n "${CGLCHECK_DRY_RUN}" ]; then
        RECTOR_OPTIONS="--dry-run"
    fi
    COMMAND="php -dxdebug.mode=off .Build/bin/rector process ${RECTOR_OPTIONS} --config=Build/rector/config.php"
    ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name rector-${SUFFIX} ${IMAGE_PHP} /bin/sh -c "${COMMAND}"
}

xliffLint() {
    ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name xliff-lint-${SUFFIX} ${IMAGE_PHP} php -dxdebug.mode=off Build/Scripts/checkIntegrityXliff.php
}

xliffNormalize() {
    local NORMALIZE_XLIFF_ARGS=""
    if [ -n "${CGLCHECK_DRY_RUN}" ]; then
        NORMALIZE_XLIFF_ARGS="-n"
    fi
    ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name xliff-normalize-${SUFFIX} ${IMAGE_PHP} php -dxdebug.mode=off Build/Scripts/xliffNormalizer.php ${NORMALIZE_XLIFF_ARGS} "$@"
}

# Test if at least one of the supported container binaries exists, else exit out with error
if ! type "docker" >/dev/null 2>&1 && ! type "podman" >/dev/null 2>&1; then
    echo "This script relies on docker or podman. Please install" >&2
    exit 1
fi

# Go to the directory this script is located, so everything else is relative
# to this dir, no matter from where this script is called, then go up two dirs.
THIS_SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" >/dev/null && pwd)"
cd "$THIS_SCRIPT_DIR" || exit 1
cd ../../ || exit 1
ROOT_DIR="${PWD}"

# Option defaults
TEST_SUITE="unit"
# be_groups supports TYPO3 14.3 LTS only (RELAUNCH.md, §8.3)
CORE_VERSION="14.3"
DBMS="sqlite"
DBMS_VERSION=""
PHP_VERSION="8.5"
PHP_XDEBUG_ON=0
PHP_XDEBUG_PORT=9003
PHPUNIT_RANDOM=""
CREATE_COVERAGE=0
COVERAGE_FILE=""
COVERAGE_OPTION=()
# CGLCHECK_DRY_RUN is a more generic dry-run switch not limited to CGL
CGLCHECK_DRY_RUN=""
DATABASE_DRIVER=""
CONTAINER_BIN=""
COMPOSER_ROOT_VERSION="1.0.x-dev"
# "composer config" and "composer require" rewrite the manifest they operate on. The update
# suites therefore run on a throwaway copy of "composer.json", selected with the "COMPOSER"
# environment variable, so that the tracked "composer.json" is never touched.
COMPOSER_BUILD_FILE="composer.build.json"
CONTAINER_INTERACTIVE="-it --init"
HOST_UID=$(id -u)
HOST_PID=$(id -g)
USERSET=""
SUFFIX="$RANDOM"
NETWORK="be-groups-${SUFFIX}"
CI_PARAMS="${CI_PARAMS:-}"
CONTAINER_HOST="host.docker.internal"

# Option parsing updates above default vars
# Reset in case getopts has been used previously in the shell
OPTIND=1
# Array for invalid options
INVALID_OPTIONS=()
# Simple option parsing based on getopts (! not getopt)
while getopts ":a:b:s:d:i:p:xy:o:nmhu" OPT; do
    case ${OPT} in
        s)
            TEST_SUITE=${OPTARG}
            ;;
        b)
            if ! [[ ${OPTARG} =~ ^(docker|podman)$ ]]; then
                INVALID_OPTIONS+=("${OPTARG}")
            fi
            CONTAINER_BIN=${OPTARG}
            ;;
        a)
            DATABASE_DRIVER=${OPTARG}
            ;;
        d)
            DBMS=${OPTARG}
            ;;
        i)
            DBMS_VERSION=${OPTARG}
            ;;
        p)
            PHP_VERSION=${OPTARG}
            if ! [[ ${PHP_VERSION} =~ ^(8.2|8.3|8.4|8.5)$ ]]; then
                INVALID_OPTIONS+=("${OPTARG}")
            fi
            ;;
        x)
            PHP_XDEBUG_ON=1
            ;;
        y)
            PHP_XDEBUG_PORT=${OPTARG}
            ;;
        o)
            PHPUNIT_RANDOM="--random-order-seed=${OPTARG}"
            ;;
        n)
            CGLCHECK_DRY_RUN="-n"
            ;;
        m)
            CREATE_COVERAGE=1
            ;;
        h)
            loadHelp
            echo "${HELP}"
            exit 0
            ;;
        u)
            TEST_SUITE=update
            ;;
        \?)
            INVALID_OPTIONS+=("${OPTARG}")
            ;;
        :)
            INVALID_OPTIONS+=("${OPTARG}")
            ;;
    esac
done

# Exit on invalid options
if [ ${#INVALID_OPTIONS[@]} -ne 0 ]; then
    echo "Invalid option(s):" >&2
    for I in "${INVALID_OPTIONS[@]}"; do
        echo "-"${I} >&2
    done
    echo >&2
    echo "Use \"Build/Scripts/runTests.sh -h\" to display help and valid options" >&2
    exit 1
fi

# Validated here rather than further down, so that an invalid combination exits before the
# container network is created and does not leave it behind.
if [ ${CREATE_COVERAGE} -eq 1 ] && [ ${PHP_XDEBUG_ON} -eq 1 ]; then
    echo "Options \"-m\" and \"-x\" cannot be combined, they need a different Xdebug mode." >&2
    echo >&2
    echo "Use \"Build/Scripts/runTests.sh -h\" to display help and valid options" >&2
    exit 1
fi

if [ ${CREATE_COVERAGE} -eq 1 ] && ! [[ ${TEST_SUITE} =~ ^(functional|unit|unitRandom)$ ]]; then
    echo "Option \"-m\" is not available for \"-s ${TEST_SUITE}\"." >&2
    echo >&2
    echo "Use \"Build/Scripts/runTests.sh -h\" to display help and valid options" >&2
    exit 1
fi

handleDbmsOptions

if [ "${CI}" == "true" ]; then
    # ENV var "CI" is set by GitHub Actions. Use it to force some CI details.
    CONTAINER_INTERACTIVE=""
elif [ ! -t 0 ] || [ ! -t 1 ]; then
    # If stdin or stdout is not a TTY (e.g. a script runner, pipe, or non-interactive shell),
    # drop the interactive "-it" flags automatically. Keep "--init" so the PID 1 init process
    # still forwards signals (e.g. ctrl-c) to the test process.
    CONTAINER_INTERACTIVE="--init"
fi

# determine default container binary to use: 1. podman 2. docker
if [[ -z "${CONTAINER_BIN}" ]]; then
    if type "podman" >/dev/null 2>&1; then
        CONTAINER_BIN="podman"
    elif type "docker" >/dev/null 2>&1; then
        CONTAINER_BIN="docker"
    fi
fi

if [ "$(uname)" != "Darwin" ] && [ "${CONTAINER_BIN}" = "docker" ]; then
    # Run docker jobs as current user to prevent permission issues. Not needed with podman.
    USERSET="--user $HOST_UID"
fi

if ! type ${CONTAINER_BIN} >/dev/null 2>&1; then
    echo "Selected container environment \"${CONTAINER_BIN}\" not found. Please install or use -b option to select one." >&2
    exit 1
fi

# Create .cache dir: composer need this.
mkdir -p .cache
mkdir -p .Build/public/typo3temp/var/tests

IMAGE_PHP="ghcr.io/typo3/core-testing-$(echo "php${PHP_VERSION}" | sed -e 's/\.//'):$(getPhpImageVersion ${PHP_VERSION})"
IMAGE_SHELLCHECK="docker.io/koalaman/shellcheck:v0.11.0"
IMAGE_DOCS="ghcr.io/typo3-documentation/render-guides:0.45.0"
IMAGE_MARIADB="docker.io/mariadb:${DBMS_VERSION}"
IMAGE_MYSQL="docker.io/mysql:${DBMS_VERSION}"
IMAGE_POSTGRES="docker.io/postgres:${DBMS_VERSION}-alpine"
# PostgreSQL 18 moved `PGDATA` from `/var/lib/postgresql/data` to `/var/lib/postgresql/<major>/docker`
# and refuses to start when a mount point is placed at the old location. Mounting one level above at
# `/var/lib/postgresql` is the documented recommendation for that case.
POSTGRES_TMPFS_MOUNT="/var/lib/postgresql/data"
if [ "${DBMS}" = "postgres" ] && [ "${DBMS_VERSION}" -ge 18 ]; then
    POSTGRES_TMPFS_MOUNT="/var/lib/postgresql"
fi

# Remove handled options and leaving the rest in the line, so it can be passed raw to commands
shift $((OPTIND - 1))

${CONTAINER_BIN} network create ${NETWORK} >/dev/null

# In a git worktree ".git" is a file pointing to a gitdir outside "${ROOT_DIR}",
# so the mount below does not carry it and git finds no repository inside the
# container at all. Mounting the common gitdir under its original absolute path
# covers both it and the worktree gitdir nested below it.
GIT_DIR_MOUNT=""
if [ -f "${ROOT_DIR}/.git" ]; then
    GIT_COMMON_DIR="$(git -C "${ROOT_DIR}" rev-parse --git-common-dir 2>/dev/null)"
    GIT_COMMON_DIR="$(cd "${ROOT_DIR}" && cd "${GIT_COMMON_DIR}" >/dev/null 2>&1 && pwd)"
    if [ -n "${GIT_COMMON_DIR}" ] && [ "${GIT_COMMON_DIR}" != "${ROOT_DIR}" ]; then
        GIT_DIR_MOUNT="-v ${GIT_COMMON_DIR}:${GIT_COMMON_DIR}"
    fi
fi

if [ ${CONTAINER_BIN} = "docker" ]; then
    CONTAINER_COMMON_PARAMS="${CONTAINER_INTERACTIVE} --rm --network ${NETWORK} --add-host ${CONTAINER_HOST}:host-gateway ${USERSET} -v ${ROOT_DIR}:${ROOT_DIR} ${GIT_DIR_MOUNT} -w ${ROOT_DIR}"
    # docker creates a tmpfs owned by "root:root". "uid" and "gid" make the mount owned by the
    # user the container runs as, "mode=1777" keeps it writable whatever the umask.
    TMPFS_MOUNT_OPTIONS="rw,noexec,nosuid,uid=${HOST_UID},gid=${HOST_PID},mode=1777"
else
    # podman
    CONTAINER_HOST="host.containers.internal"
    CONTAINER_COMMON_PARAMS="${CONTAINER_INTERACTIVE} ${CI_PARAMS} --rm --network ${NETWORK} -v ${ROOT_DIR}:${ROOT_DIR} ${GIT_DIR_MOUNT} -w ${ROOT_DIR}"
    # Rootless podman maps the container root to the host user, so the tmpfs is writable without
    # an explicit owner. "mode=1777" is kept for the rootful case.
    TMPFS_MOUNT_OPTIONS="rw,noexec,nosuid,mode=1777"
fi

COMPOSER_PARAMS="-e COMPOSER_CACHE_DIR=.cache/composer -e COMPOSER_HOME=${ROOT_DIR}/.cache/composer-home -e COMPOSER_ROOT_VERSION=${COMPOSER_ROOT_VERSION}"

if [ ${CREATE_COVERAGE} -eq 1 ]; then
    # Xdebug is the only coverage driver in the "core-testing" images; PCOV is not installed there.
    XDEBUG_MODE="-e XDEBUG_MODE=coverage"
    XDEBUG_CONFIG=" "
elif [ ${PHP_XDEBUG_ON} -eq 0 ]; then
    XDEBUG_MODE="-e XDEBUG_MODE=off"
    XDEBUG_CONFIG=" "
else
    XDEBUG_MODE="-e XDEBUG_MODE=debug -e XDEBUG_TRIGGER=foo"
    XDEBUG_CONFIG="client_port=${PHP_XDEBUG_PORT} client_host=${CONTAINER_HOST}"
fi

# Suite execution
case ${TEST_SUITE} in
    cgl)
        cgl
        SUITE_EXIT_CODE=$?
        ;;
    clean)
        cleanCacheFiles
        cleanRenderedDocumentationFiles
        cleanTestFiles
        SUITE_EXIT_CODE=0
        ;;
    cleanCache)
        cleanCacheFiles
        SUITE_EXIT_CODE=0
        ;;
    cleanDocs)
        cleanRenderedDocumentationFiles
        SUITE_EXIT_CODE=0
        ;;
    cleanTests)
        cleanTestFiles
        SUITE_EXIT_CODE=0
        ;;
    composer)
        COMMAND=(composer "$@")
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name composer-${SUFFIX} ${COMPOSER_PARAMS} ${IMAGE_PHP} "${COMMAND[@]}"
        SUITE_EXIT_CODE=$?
        ;;
    composerNormalize)
        composerNormalize
        SUITE_EXIT_CODE=$?
        ;;
    composerUpdateMax)
        COMMAND="cp composer.json ${COMPOSER_BUILD_FILE} && (composer config --unset platform.php && composer require --no-ansi --no-interaction --no-progress --no-install typo3/minimal:^${CORE_VERSION} && composer update --no-progress --no-interaction && composer show); COMPOSER_EXIT_CODE=\$?; rm -f ${COMPOSER_BUILD_FILE}; exit \$COMPOSER_EXIT_CODE"
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name composer-update-max-${SUFFIX} -e COMPOSER=${COMPOSER_BUILD_FILE} ${COMPOSER_PARAMS} ${IMAGE_PHP} /bin/sh -c "${COMMAND}"
        SUITE_EXIT_CODE=$?
        ;;
    composerUpdateDev)
        # The TYPO3 extension for PHPStan and the TYPO3 Rector rules only support released core versions.
        DEV_PACKAGES="typo3/cms-core:dev-main typo3/cms-backend:dev-main"
        DEV_DEV_PACKAGES="typo3/cms-dashboard:dev-main typo3/cms-workspaces:dev-main typo3/testing-framework:dev-main phpunit/phpunit:^12.5"
        COMMAND="cp composer.json ${COMPOSER_BUILD_FILE} && (composer config --unset platform.php && composer config minimum-stability dev && composer config prefer-stable true && composer remove --dev --no-update saschaegerer/phpstan-typo3 ssch/typo3-rector && composer require --no-update ${DEV_PACKAGES} && composer require --dev --no-update ${DEV_DEV_PACKAGES} && composer update --no-progress --no-interaction && composer show typo3/cms-core); COMPOSER_EXIT_CODE=\$?; rm -f ${COMPOSER_BUILD_FILE}; exit \$COMPOSER_EXIT_CODE"
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name composer-update-dev-${SUFFIX} -e COMPOSER=${COMPOSER_BUILD_FILE} ${COMPOSER_PARAMS} ${IMAGE_PHP} /bin/sh -c "${COMMAND}"
        SUITE_EXIT_CODE=$?
        ;;
    composerUpdateMin)
        COMMAND="cp composer.json ${COMPOSER_BUILD_FILE} && (composer config platform.php ${PHP_VERSION}.0 && composer require --no-ansi --no-interaction --no-progress --no-install typo3/minimal:^${CORE_VERSION} && composer update --prefer-lowest --no-progress --no-interaction && composer show); COMPOSER_EXIT_CODE=\$?; rm -f ${COMPOSER_BUILD_FILE}; exit \$COMPOSER_EXIT_CODE"
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name composer-update-min-${SUFFIX} -e COMPOSER=${COMPOSER_BUILD_FILE} ${COMPOSER_PARAMS} ${IMAGE_PHP} /bin/sh -c "${COMMAND}"
        SUITE_EXIT_CODE=$?
        ;;
    coverageMerge)
        COVERAGE_REPORTS=()
        for COVERAGE_REPORT in "${ROOT_DIR}"/.Build/coverage/*.cov; do
            [ -e "${COVERAGE_REPORT}" ] || continue
            COVERAGE_REPORTS+=("$(basename "${COVERAGE_REPORT}")")
        done
        if [ ! -x "${ROOT_DIR}/.Build/bin/phpcov" ]; then
            echo "\".Build/bin/phpcov\" is missing, install \"phpunit/phpcov\" first." >&2
            SUITE_EXIT_CODE=1
        elif [ ${#COVERAGE_REPORTS[@]} -eq 0 ]; then
            echo "No coverage reports in \".Build/coverage/\" to merge." >&2
            echo "Run a test suite with \"-m\" first." >&2
            SUITE_EXIT_CODE=1
        else
            # Every report has the PHP version in its name. Reports of different PHP versions
            # (left over from an earlier run with another "-p") must not be merged.
            COVERAGE_VARIANTS=$(printf '%s\n' "${COVERAGE_REPORTS[@]}" | sed -e 's/\.cov$//' -e 's/^unit-random-//' -e 's/^unit-//' -e 's/^functional-//' | cut -d- -f1 | sort -u)
            if [ "$(printf '%s\n' "${COVERAGE_VARIANTS}" | wc -l)" -ne 1 ]; then
                echo "The coverage reports in \".Build/coverage/\" have not all been collected for the" >&2
                echo "same PHP version:" >&2
                printf '    %s\n' "${COVERAGE_REPORTS[@]}" >&2
                echo "Remove the stale reports, or run \"-s cleanTests\", and collect the coverage again." >&2
                SUITE_EXIT_CODE=1
            else
                mkdir -p "${ROOT_DIR}/.Build/logs"
                COMMAND=(.Build/bin/phpcov merge --clover=.Build/logs/clover.xml .Build/coverage/)
                ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name coverage-merge-${SUFFIX} ${IMAGE_PHP} "${COMMAND[@]}"
                SUITE_EXIT_CODE=$?
            fi
        fi
        ;;
    docs)
        mkdir -p Documentation-GENERATED-temp
        ${CONTAINER_BIN} run ${CONTAINER_INTERACTIVE} --rm ${USERSET} -v "${ROOT_DIR}":/project ${IMAGE_DOCS} --fail-on-log --fail-on-error --no-progress --config=Documentation
        SUITE_EXIT_CODE=$?
        ;;
    fix)
        composerNormalize
        SUITE_EXIT_CODE=$?
        rector
        SUITE_EXIT_CODE=$((SUITE_EXIT_CODE + $?))
        cgl
        SUITE_EXIT_CODE=$((SUITE_EXIT_CODE + $?))
        xliffNormalize
        SUITE_EXIT_CODE=$((SUITE_EXIT_CODE + $?))
        ;;
    functional)
        # The DBMS, its version and the driver are part of the name, so that the coverage of
        # the functional runs of a test matrix can be merged without overwriting each other.
        COVERAGE_FILE="functional-php${PHP_VERSION//./}-${DBMS}"
        [ -n "${DBMS_VERSION}" ] && COVERAGE_FILE="${COVERAGE_FILE}${DBMS_VERSION//./_}"
        [ -n "${DATABASE_DRIVER}" ] && COVERAGE_FILE="${COVERAGE_FILE}_${DATABASE_DRIVER}"
        COVERAGE_FILE="${COVERAGE_FILE}.cov"
        prepareCoverage
        COMMAND=(.Build/bin/phpunit -c Build/phpunit/FunctionalTests.xml --exclude-group not-${DBMS} "${COVERAGE_OPTION[@]}" "$@")
        case ${DBMS} in
            mariadb)
                echo "Using driver: ${DATABASE_DRIVER}"
                ${CONTAINER_BIN} run --rm ${CI_PARAMS} --name mariadb-func-${SUFFIX} --network ${NETWORK} -d -e MYSQL_ROOT_PASSWORD=funcp --tmpfs /var/lib/mysql/:rw,noexec,nosuid ${IMAGE_MARIADB} >/dev/null
                waitFor mariadb-func-${SUFFIX} 3306
                CONTAINERPARAMS="-e typo3DatabaseDriver=${DATABASE_DRIVER} -e typo3DatabaseName=func_test -e typo3DatabaseUsername=root -e typo3DatabaseHost=mariadb-func-${SUFFIX} -e typo3DatabasePassword=funcp"
                ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name functional-${SUFFIX} ${XDEBUG_MODE} -e XDEBUG_CONFIG="${XDEBUG_CONFIG}" ${CONTAINERPARAMS} ${IMAGE_PHP} "${COMMAND[@]}"
                SUITE_EXIT_CODE=$?
                ;;
            mysql)
                echo "Using driver: ${DATABASE_DRIVER}"
                ${CONTAINER_BIN} run --rm ${CI_PARAMS} --name mysql-func-${SUFFIX} --network ${NETWORK} -d -e MYSQL_ROOT_PASSWORD=funcp --tmpfs /var/lib/mysql/:rw,noexec,nosuid ${IMAGE_MYSQL} >/dev/null
                waitFor mysql-func-${SUFFIX} 3306
                CONTAINERPARAMS="-e typo3DatabaseDriver=${DATABASE_DRIVER} -e typo3DatabaseName=func_test -e typo3DatabaseUsername=root -e typo3DatabaseHost=mysql-func-${SUFFIX} -e typo3DatabasePassword=funcp"
                ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name functional-${SUFFIX} ${XDEBUG_MODE} -e XDEBUG_CONFIG="${XDEBUG_CONFIG}" ${CONTAINERPARAMS} ${IMAGE_PHP} "${COMMAND[@]}"
                SUITE_EXIT_CODE=$?
                ;;
            postgres)
                ${CONTAINER_BIN} run --rm ${CI_PARAMS} --name postgres-func-${SUFFIX} --network ${NETWORK} -d -e POSTGRES_PASSWORD=funcp -e POSTGRES_USER=funcu --tmpfs ${POSTGRES_TMPFS_MOUNT}:rw,noexec,nosuid ${IMAGE_POSTGRES} >/dev/null
                waitFor postgres-func-${SUFFIX} 5432
                CONTAINERPARAMS="-e typo3DatabaseDriver=pdo_pgsql -e typo3DatabaseName=bamboo -e typo3DatabaseUsername=funcu -e typo3DatabaseHost=postgres-func-${SUFFIX} -e typo3DatabasePassword=funcp"
                ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name functional-${SUFFIX} ${XDEBUG_MODE} -e XDEBUG_CONFIG="${XDEBUG_CONFIG}" ${CONTAINERPARAMS} ${IMAGE_PHP} "${COMMAND[@]}"
                SUITE_EXIT_CODE=$?
                ;;
            sqlite)
                # The functional sqlite databases are written to a tmpfs, which roughly halves the
                # runtime of the suite and leaves nothing behind on disk.
                mkdir -p "${ROOT_DIR}/.Build/public/typo3temp/var/tests/functional-sqlite-dbs/"
                CONTAINERPARAMS="-e typo3DatabaseDriver=pdo_sqlite --tmpfs ${ROOT_DIR}/.Build/public/typo3temp/var/tests/functional-sqlite-dbs/:${TMPFS_MOUNT_OPTIONS}"
                ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name functional-${SUFFIX} ${XDEBUG_MODE} -e XDEBUG_CONFIG="${XDEBUG_CONFIG}" ${CONTAINERPARAMS} ${IMAGE_PHP} "${COMMAND[@]}"
                SUITE_EXIT_CODE=$?
                ;;
        esac
        ;;
    integrity)
        integrity
        SUITE_EXIT_CODE=$?
        ;;
    lint)
        lintPhp
        SUITE_EXIT_CODE=$?
        lintJson
        SUITE_EXIT_CODE=$((SUITE_EXIT_CODE + $?))
        lintYaml
        SUITE_EXIT_CODE=$((SUITE_EXIT_CODE + $?))
        ;;
    lintJson)
        lintJson
        SUITE_EXIT_CODE=$?
        ;;
    lintPhp)
        lintPhp
        SUITE_EXIT_CODE=$?
        ;;
    lintYaml)
        lintYaml
        SUITE_EXIT_CODE=$?
        ;;
    phpstan)
        phpstan "$@"
        SUITE_EXIT_CODE=$?
        ;;
    psrVerify)
        psrVerify
        SUITE_EXIT_CODE=$?
        ;;
    rector)
        rector
        SUITE_EXIT_CODE=$?
        ;;
    shellcheck)
        ${CONTAINER_BIN} run ${CONTAINER_INTERACTIVE} --rm ${USERSET} -v "${ROOT_DIR}":/project:ro ${IMAGE_SHELLCHECK} /project/Build/Scripts/runTests.sh
        SUITE_EXIT_CODE=$?
        ;;
    unit)
        COVERAGE_FILE="unit-php${PHP_VERSION//./}.cov"
        prepareCoverage
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name unit-${SUFFIX} ${XDEBUG_MODE} -e XDEBUG_CONFIG="${XDEBUG_CONFIG}" ${IMAGE_PHP} .Build/bin/phpunit -c Build/phpunit/UnitTests.xml "${COVERAGE_OPTION[@]}" "$@"
        SUITE_EXIT_CODE=$?
        ;;
    unitRandom)
        COVERAGE_FILE="unit-random-php${PHP_VERSION//./}.cov"
        prepareCoverage
        ${CONTAINER_BIN} run ${CONTAINER_COMMON_PARAMS} --name unit-random-${SUFFIX} ${XDEBUG_MODE} -e XDEBUG_CONFIG="${XDEBUG_CONFIG}" ${IMAGE_PHP} .Build/bin/phpunit -c Build/phpunit/UnitTests.xml "${COVERAGE_OPTION[@]}" --order-by=random ${PHPUNIT_RANDOM} "$@"
        SUITE_EXIT_CODE=$?
        ;;
    update)
        # pull pinned typo3/core-testing-* images of all supported PHP versions
        echo "> pull ghcr.io/typo3/core-testing-* images of all supported PHP versions"
        for UPDATE_PHP_VERSION in 8.2 8.3 8.4 8.5; do
            ${CONTAINER_BIN} pull "ghcr.io/typo3/core-testing-$(echo "php${UPDATE_PHP_VERSION}" | sed -e 's/\.//'):$(getPhpImageVersion ${UPDATE_PHP_VERSION})"
        done
        echo ""
        # remove "dangling" typo3/core-testing-* images (those tagged as <none>)
        echo "> remove \"dangling\" ghcr.io/typo3/core-testing-* images (those tagged as <none>)"
        ${CONTAINER_BIN} images --filter "reference=ghcr.io/typo3/core-testing-*" --filter "dangling=true" --format "{{.ID}}" | xargs -I {} ${CONTAINER_BIN} rmi {}
        echo ""
        # remove "dangling" volumes
        echo "> remove \"dangling\" volumes"
        ${CONTAINER_BIN} volume ls --filter "dangling=true" --format "{{.Name}}" | xargs -I {} ${CONTAINER_BIN} volume rm {}
        echo ""
        SUITE_EXIT_CODE=0
        ;;
    xliffLint)
        xliffLint
        SUITE_EXIT_CODE=$?
        ;;
    xliffNormalize)
        xliffNormalize "$@"
        SUITE_EXIT_CODE=$?
        ;;
    *)
        loadHelp
        echo "Invalid -s option argument ${TEST_SUITE}" >&2
        echo >&2
        echo "${HELP}" >&2
        if [ ${CONTAINER_BIN} = "docker" ]; then
            ${CONTAINER_BIN} network rm ${NETWORK} >/dev/null
        else
            ${CONTAINER_BIN} network rm -f ${NETWORK} >/dev/null
        fi
        exit 1
        ;;
esac

# Cleanup, print summary && exit with exitcode
printSummary
