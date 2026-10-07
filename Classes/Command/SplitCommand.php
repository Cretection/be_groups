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

use Cretection\BeGroups\Domain\Classification\Classification;
use Cretection\BeGroups\Domain\Classification\ClassificationAction;
use Cretection\BeGroups\Domain\Classification\ConversionStatus;
use Cretection\BeGroups\Domain\Classification\GroupClassifier;
use Cretection\BeGroups\Domain\Classification\GroupConverter;
use Cretection\BeGroups\Domain\Kind\GroupKind;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Core\Bootstrap;

/**
 * Splits classic groups into building blocks and a role. The group keeps its uid as role,
 * so users and other groups keep their assignments. Effective permissions never change;
 * see GroupConverter.
 *
 * @internal
 */
#[AsCommand('begroups:split', 'Splits classic groups into building blocks and a role that keeps the uid of the group.')]
final class SplitCommand extends Command
{
    public function __construct(
        private readonly GroupClassifier $groupClassifier,
        private readonly GroupConverter $groupConverter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('uids', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'The uids of the classic groups to split')
            ->addOption('all', null, InputOption::VALUE_NONE, 'Split all classic groups that begroups:classify proposes to split')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only show what would be created, change nothing')
            ->setHelp(
                'The permissions of the group move into one building block per kind; existing building blocks that '
                . 'grant exactly the same are reused (except for TSconfig). The group becomes a role containing its '
                . 'former subgroups followed by the building blocks, so the precedence of TSconfig stays the same. '
                . 'Effective permissions never change: every split is verified and rolled back otherwise.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $uids = $this->getUids($input);
        $all = $input->getOption('all') === true;
        if ($uids === null || ($uids === [] && !$all) || ($uids !== [] && $all)) {
            $io->error('Name the groups to split by uid, or use --all.');
            return Command::INVALID;
        }
        $io->title('Split classic groups');

        $failed = false;
        $classifications = [];
        foreach ($all ? $this->groupClassifier->classifyAll() : $uids as $item) {
            $classification = $item instanceof Classification ? $item : $this->groupClassifier->classify($item);
            if ($classification === null) {
                $failed = true;
                $io->writeln(sprintf('be_groups:%d: skipped, no classic group.', $item));
            } elseif ($classification->action === ClassificationAction::Split) {
                $classifications[] = $classification;
            } elseif (!$all) {
                $failed = true;
                $io->writeln(ConsoleText::escape(sprintf('be_groups:%d %s: skipped, %s', $classification->uid, $classification->title, $classification->reason)));
            }
        }
        if ($classifications === []) {
            $io->success('There is no group to split.');
            return $failed ? Command::FAILURE : Command::SUCCESS;
        }
        $io->table(['Group', 'Building block', 'Permissions'], $this->describePlan($classifications));

        if ($input->getOption('dry-run') === true) {
            $io->note(sprintf('%d group(s) would be split. Nothing was changed (--dry-run).', count($classifications)));
            return $failed ? Command::FAILURE : Command::SUCCESS;
        }
        Bootstrap::initializeBackendAuthentication();
        foreach ($classifications as $classification) {
            $result = $this->groupConverter->split($classification->uid);
            $failed = $failed || $result->status !== ConversionStatus::Converted;
            $io->writeln(ConsoleText::escape(sprintf(
                'be_groups:%d %s: %s %s%s',
                $result->uid,
                $classification->title,
                $result->status->value,
                $result->message,
                $result->createdBlockUids !== [] ? ' Created: ' . implode(', ', $result->createdBlockUids) . '.' : '',
            )));
        }
        if ($failed) {
            $io->error('At least one group was not split and was left unchanged.');
            return Command::FAILURE;
        }
        $io->success(sprintf('%d group(s) split. Check the result with begroups:audit.', count($classifications)));
        return Command::SUCCESS;
    }

    /**
     * @return list<int>|null null if an argument is no uid
     */
    private function getUids(InputInterface $input): ?array
    {
        $arguments = $input->getArgument('uids');
        $uids = [];
        foreach (is_array($arguments) ? $arguments : [] as $argument) {
            if (!is_string($argument) || preg_match('/^[1-9]\d*$/', $argument) !== 1) {
                return null;
            }
            $uids[] = (int)$argument;
        }
        return $uids;
    }

    /**
     * @param list<Classification> $classifications
     * @return list<list<string>>
     */
    private function describePlan(array $classifications): array
    {
        $rows = [];
        foreach ($classifications as $classification) {
            $group = sprintf('be_groups:%d %s%s', $classification->uid, $classification->title, $classification->hidden ? ' (disabled)' : '');
            foreach ($classification->concerns as $concern) {
                $rows[] = array_map(ConsoleText::escape(...), [
                    $group,
                    $concern->reusableBlockUid === null
                        ? sprintf('new %s%s', GroupKind::tryFrom($concern->kind)?->prefix() ?? strtoupper($concern->kind) . '_', $classification->title)
                        : sprintf('existing be_groups:%d', $concern->reusableBlockUid),
                    implode(', ', array_keys($concern->values)),
                ]);
                $group = '';
            }
            $rows[] = array_map(ConsoleText::escape(...), [$group, 'the group becomes a role', '']);
        }
        return $rows;
    }
}
