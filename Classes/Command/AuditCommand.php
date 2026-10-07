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

namespace Cretection\BeGroups\Command;

use Cretection\BeGroups\Domain\Audit\AuditFinding;
use Cretection\BeGroups\Domain\Audit\AuditSeverity;
use Cretection\BeGroups\Domain\Audit\PermissionAudit;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Checks backend groups and users against the role model.
 *
 * Exit code 0: no errors (warnings allowed, unless --fail-on-warnings is set), 1: findings to fix.
 * Read-only: nothing is changed.
 *
 * @internal
 */
#[AsCommand('begroups:audit', 'Checks backend groups and users against the role model (read-only).')]
final class AuditCommand extends Command
{
    private const FORMAT_TEXT = 'text';
    private const FORMAT_JSON = 'json';

    public function __construct(
        private readonly PermissionAudit $permissionAudit,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format: "text" or "json"', self::FORMAT_TEXT)
            ->addOption('fail-on-warnings', null, InputOption::VALUE_NONE, 'Return exit code 1 for warnings as well')
            ->setHelp(
                'Finds everything that does not follow the role model, e.g. data written around the DataHandler, '
                . 'groups with unknown kinds, building blocks assigned to users, roles with invalid members and '
                . 'permissions on user records. Use it in deployments or monitoring; nothing is changed.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $format = $input->getOption('format');
        if ($format !== self::FORMAT_TEXT && $format !== self::FORMAT_JSON) {
            (new SymfonyStyle($input, $output))->error('The option "--format" must be "text" or "json".');
            return Command::INVALID;
        }

        $findings = $this->permissionAudit->run();
        $errors = count(array_filter($findings, static fn(AuditFinding $finding): bool => $finding->severity === AuditSeverity::Error));
        $warnings = count($findings) - $errors;

        if ($format === self::FORMAT_JSON) {
            $output->writeln(json_encode([
                'errors' => $errors,
                'warnings' => $warnings,
                'findings' => array_map(static fn(AuditFinding $finding): array => [
                    'severity' => $finding->severity->value,
                    'identifier' => $finding->identifier,
                    'table' => $finding->table,
                    'uid' => $finding->uid,
                    'title' => $finding->title,
                    'message' => $finding->message,
                ], $findings),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR));
        } else {
            $this->renderText(new SymfonyStyle($input, $output), $findings, $errors, $warnings);
        }

        $failOnWarnings = $input->getOption('fail-on-warnings') === true;
        return $errors > 0 || ($failOnWarnings && $warnings > 0) ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @param list<AuditFinding> $findings
     */
    private function renderText(SymfonyStyle $io, array $findings, int $errors, int $warnings): void
    {
        $io->title('Backend groups and users');
        if ($findings === []) {
            $io->success('Everything follows the role model.');
            return;
        }
        $io->table(
            ['Severity', 'Record', 'Check', 'Finding'],
            array_map(static fn(AuditFinding $finding): array => array_map(self::sanitize(...), [
                $finding->severity->value,
                sprintf('%s:%d %s', $finding->table, $finding->uid, $finding->title),
                $finding->identifier,
                $finding->message,
            ]), $findings),
        );
        $summary = sprintf('%d error(s), %d warning(s).', $errors, $warnings);
        $errors > 0 ? $io->error($summary) : $io->warning($summary);
    }

    /**
     * Titles are data of editors: they must neither be read as console formatting
     * nor carry control characters (e.g. ANSI escape sequences) into the terminal.
     */
    private static function sanitize(string $value): string
    {
        // Invalid UTF-8 makes the first pattern fail; then every byte outside of printable ASCII is replaced.
        $printable = preg_replace('/[\x00-\x1F\x7F\x{80}-\x{9F}]/u', ' ', $value)
            ?? preg_replace('/[^\x20-\x7E]/', '?', $value);
        return OutputFormatter::escape($printable ?? '');
    }
}
