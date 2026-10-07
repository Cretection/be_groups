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

// Adapted from the TYPO3 Core 14.3 (Build/Scripts/phpIntegrityChecker.php).
// Checks (CODING_GUIDELINES.md, §3.7 and §13):
// - every thrown exception has a unique 10 digit (Unix timestamp) code
// - test classes are final
// - test methods do not start with "test" (the #[Test] attribute is used)
// - no annotations besides the allowed phpDoc tags (e.g. no PHPUnit annotations)
//
// Usage: php Build/Scripts/phpIntegrityChecker.php [--php=8.2]

use Cretection\BeGroups\Build\PhpIntegrityChecks\AbstractPhpIntegrityChecker;
use Cretection\BeGroups\Build\PhpIntegrityChecks\AnnotationChecker;
use Cretection\BeGroups\Build\PhpIntegrityChecks\ExceptionCodeChecker;
use Cretection\BeGroups\Build\PhpIntegrityChecks\NodeResolver\ExceptionConstructorResolver;
use Cretection\BeGroups\Build\PhpIntegrityChecks\TestClassFinalChecker;
use Cretection\BeGroups\Build\PhpIntegrityChecks\TestMethodPrefixChecker;
use PhpParser\Error;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\Parser;
use PhpParser\ParserFactory;
use PhpParser\PhpVersion;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\SingleCommandApplication;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Finder\Finder;

if (PHP_SAPI !== 'cli') {
    die('Script must be called from command line.' . chr(10));
}

require __DIR__ . '/../../.Build/vendor/autoload.php';

// The checkers are build tooling, not part of the extension, so they are not autoloaded.
require_once __DIR__ . '/phpIntegrityChecks/AbstractPhpIntegrityChecker.php';
require_once __DIR__ . '/phpIntegrityChecks/AnnotationChecker.php';
require_once __DIR__ . '/phpIntegrityChecks/ExceptionCodeChecker.php';
require_once __DIR__ . '/phpIntegrityChecks/TestClassFinalChecker.php';
require_once __DIR__ . '/phpIntegrityChecks/TestMethodPrefixChecker.php';
require_once __DIR__ . '/phpIntegrityChecks/NodeResolver/ExceptionConstructorResolver.php';

final class PhpIntegrityChecker
{
    /**
     * @var list<class-string<AbstractPhpIntegrityChecker>>
     */
    private const REGISTERED_VISITORS = [
        AnnotationChecker::class,
        TestMethodPrefixChecker::class,
        TestClassFinalChecker::class,
        ExceptionCodeChecker::class,
    ];

    /**
     * @var list<string>
     */
    private const DIRECTORIES = [
        'Build/Scripts',
        'Classes',
        'Tests/Unit',
        'Tests/Functional',
    ];

    /**
     * @var array<class-string<AbstractPhpIntegrityChecker>, AbstractPhpIntegrityChecker>
     */
    private array $visitors = [];

    /**
     * @var array<string, array<int|string, mixed>>
     */
    private array $issues = [];

    public function __invoke(InputInterface $input, OutputInterface $output): int
    {
        $version = explode('.', (string)$input->getOption('php'));
        $parser = (new ParserFactory())->createForVersion(PhpVersion::fromComponents((int)$version[0], (int)($version[1] ?? 0)));
        $io = new SymfonyStyle($input, $output);

        foreach (self::REGISTERED_VISITORS as $visitorClassName) {
            $this->visitors[$visitorClassName] = new $visitorClassName();
            $this->issues[$visitorClassName] = [];
        }

        foreach ($this->createFinder() as $file) {
            $this->processFile($parser, $file);
        }

        return $this->displayIssues($io);
    }

    private function createFinder(): Finder
    {
        $rootDirectory = dirname(__DIR__, 2);
        $directories = array_values(array_filter(
            array_map(static fn(string $directory): string => $rootDirectory . '/' . $directory, self::DIRECTORIES),
            is_dir(...),
        ));
        return (new Finder())
            ->files()
            ->in($directories)
            // Fixture extensions of the functional tests are configuration, not code
            ->notPath('Fixtures/Extensions')
            ->name('*.php')
            ->sortByName();
    }

    private function processFile(Parser $parser, \SplFileInfo $file): void
    {
        try {
            $ast = $parser->parse((string)file_get_contents($file->getPathname())) ?? [];
        } catch (Error $error) {
            $this->issues['parsing'][$file->getRealPath()] = 'Parse error: ' . $error->getMessage();
            return;
        }

        $enrichingTraverser = new NodeTraverser();
        $enrichingTraverser->addVisitor(new NameResolver());
        $enrichingTraverser->addVisitor(new ExceptionConstructorResolver());
        $ast = $enrichingTraverser->traverse($ast);

        $usedVisitors = [];
        $traverser = new NodeTraverser();
        foreach ($this->visitors as $visitorClassName => $visitor) {
            if (!$visitor->canHandle($file)) {
                continue;
            }
            $usedVisitors[$visitorClassName] = $visitor;
            $visitor->startProcessing($file);
            $traverser->addVisitor($visitor);
        }
        $traverser->traverse($ast);

        foreach ($usedVisitors as $visitorClassName => $visitor) {
            $visitor->finishProcessing();
            $messages = $visitor->getMessages();
            if ($messages !== []) {
                $this->issues[$visitorClassName] = $messages;
            }
        }
    }

    private function displayIssues(SymfonyStyle $io): int
    {
        $exitCode = Command::SUCCESS;
        foreach ($this->issues as $visitorClassName => $issueCollection) {
            if ($issueCollection !== []) {
                $exitCode = Command::FAILURE;
            }
            // PHP syntax parsing errors are not a visitor, and thus need adjusted handling here.
            if ($visitorClassName === 'parsing') {
                $io->title('Parsing errors');
                $io->error('Following files were not checked:');
                foreach ($issueCollection as $file => $issue) {
                    $io->writeln('  > ' . $file . ': ' . $issue);
                }
                continue;
            }
            $this->visitors[$visitorClassName]->outputResult($io, $issueCollection);
            $io->newLine();
        }
        return $exitCode;
    }
}

(new SingleCommandApplication())
    ->setName('be_groups PHP integrity checker')
    ->addOption('php', 'p', InputOption::VALUE_REQUIRED, 'The PHP version to parse the code for, like 8.2', '8.2')
    ->setCode(new PhpIntegrityChecker())
    ->run();
