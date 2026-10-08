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
use Cretection\BeGroups\Domain\Classification\Concern;
use Cretection\BeGroups\Domain\Classification\ConversionStatus;
use Cretection\BeGroups\Domain\Classification\GroupClassifier;
use Cretection\BeGroups\Domain\Classification\GroupConverter;
use Cretection\BeGroups\Utility\ConsoleTextUtility;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Core\Bootstrap;

/**
 * Proposes a kind for every classic group and changes the kind of the groups that already
 * serve one purpose. Effective permissions never change; see GroupConverter.
 *
 * @internal
 */
#[AsCommand('begroups:classify', 'Proposes a kind for every classic group and changes the kind where nothing else changes.')]
final class ClassifyCommand extends Command
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
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only show the proposals, change nothing')
            ->setHelp(
                'A classic group whose permissions belong to one kind of building block, that grants nothing but '
                . 'combines other groups (role) or that only owns pages (page group) changes its kind. Groups that '
                . 'need to be split are listed for begroups:split, the others need a decision of an administrator. '
                . 'Effective permissions never change: every change is verified and rolled back otherwise.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Classify classic groups');

        $classifications = $this->groupClassifier->classifyAll();
        if ($classifications === []) {
            $io->success('There are no classic groups.');
            return Command::SUCCESS;
        }
        $io->table(
            ['Group', 'Proposal', 'Reason'],
            array_map(static fn(Classification $classification): array => [
                ConsoleTextUtility::escape(sprintf('be_groups:%d %s%s', $classification->uid, $classification->title, $classification->hidden ? ' (disabled)' : '')),
                ConsoleTextUtility::escape(self::describeProposal($classification)),
                ConsoleTextUtility::escape($classification->reason),
            ], $classifications),
        );

        $toChange = array_values(array_filter(
            $classifications,
            static fn(Classification $classification): bool => $classification->action === ClassificationAction::ChangeKind,
        ));
        $toSplit = count(array_filter($classifications, static fn(Classification $classification): bool => $classification->action === ClassificationAction::Split));
        $failed = false;
        if ($input->getOption('dry-run') === true) {
            $io->note(sprintf('%d group(s) would change their kind. Nothing was changed (--dry-run).', count($toChange)));
        } elseif ($toChange !== []) {
            Bootstrap::initializeBackendAuthentication();
            foreach ($toChange as $classification) {
                $result = $this->groupConverter->changeKind($classification->uid);
                $failed = $failed || $result->status === ConversionStatus::Failed;
                $io->writeln(ConsoleTextUtility::escape(sprintf('be_groups:%d %s: %s %s', $result->uid, $classification->title, $result->status->value, $result->message)));
            }
        }
        if ($toSplit > 0) {
            $io->note(sprintf('%d group(s) can be split into building blocks and a role: begroups:split --all --dry-run', $toSplit));
        }
        if ($failed) {
            $io->error('At least one group could not be changed and was left unchanged.');
            return Command::FAILURE;
        }
        return Command::SUCCESS;
    }

    private static function describeProposal(Classification $classification): string
    {
        return match ($classification->action) {
            ClassificationAction::ChangeKind => 'kind: ' . $classification->targetKind,
            ClassificationAction::Split => 'split: ' . implode(', ', array_map(static fn(Concern $concern): string => $concern->kind, $classification->concerns)),
            ClassificationAction::Manual => 'manual',
        };
    }
}
