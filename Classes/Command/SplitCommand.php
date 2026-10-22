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
use Cretection\BeGroups\Domain\Kind\KindPrefix;
use Cretection\BeGroups\Domain\Repository\BackendGroupRepository;
use Cretection\BeGroups\Utility\ConsoleTextUtility;
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
        private readonly KindPrefix $kindPrefix,
        private readonly BackendGroupRepository $backendGroupRepository,
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
                'The permissions of the group move into one new building block per kind. The group becomes a role '
                . 'containing its former subgroups followed by the building blocks, so the precedence of TSconfig stays '
                . 'the same. If the group would be the owner group of the pages its users create (it has no active '
                . 'subgroups), a new page group without permissions becomes its first member and takes over this task. '
                . 'Effective permissions never change: every split is verified for all users and rolled back otherwise.',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $uids = $this->getUids($input);
        if (is_string($uids)) {
            $io->error(ConsoleTextUtility::escape(sprintf('"%s" is no uid. Name the groups to split by uid, or use --all.', $uids)));
            return Command::INVALID;
        }
        $all = $input->getOption('all') === true;
        if ($uids === [] && !$all) {
            $io->error('Name the groups to split by uid, or use --all.');
            return Command::INVALID;
        }
        if ($uids !== [] && $all) {
            $io->error('Name the groups to split by uid or use --all, not both.');
            return Command::INVALID;
        }
        $io->title('Split classic groups');

        $skipped = 0;
        $proposals = [];
        foreach ($this->groupClassifier->classifyAll() as $proposal) {
            $proposals[$proposal->uid] = $proposal;
        }
        $classifications = [];
        foreach ($all ? $proposals : $uids as $item) {
            $classification = $item instanceof Classification ? $item : ($proposals[$item] ?? null);
            if ($classification === null) {
                $skipped++;
                $io->writeln(ConsoleTextUtility::escape($this->describeGroupThatIsNotClassic($item)));
            } elseif ($classification->action === ClassificationAction::Split) {
                $classifications[] = $classification;
            } elseif (!$all) {
                $skipped++;
                $io->writeln(ConsoleTextUtility::escape(sprintf(
                    'be_groups:%d %s%s: skipped. %s%s',
                    $classification->uid,
                    $classification->title,
                    $classification->hidden ? ' (disabled)' : '',
                    $classification->reason,
                    // begroups:audit asks to convert every classic group, so name the assistant that does it.
                    $classification->action === ClassificationAction::ChangeKind
                        ? sprintf(' begroups:classify changes its kind to "%s".', $classification->targetKind)
                        : '',
                )));
            }
        }
        if ($classifications === []) {
            if ($skipped > 0) {
                $io->error('None of the named groups can be split.');
                return Command::FAILURE;
            }
            $io->success('There is no group to split.');
            return Command::SUCCESS;
        }
        $io->table(['Group', 'Building block', 'Permissions'], $this->describePlan($classifications));

        if ($input->getOption('dry-run') === true) {
            $io->note(sprintf('%d group(s) would be split. Nothing was changed (--dry-run).', count($classifications)));
            if ($skipped > 0) {
                $io->error(sprintf('%d of the named groups cannot be split, see above.', $skipped));
                return Command::FAILURE;
            }
            return Command::SUCCESS;
        }
        Bootstrap::initializeBackendAuthentication();
        $split = 0;
        foreach ($classifications as $planned) {
            $result = $this->groupConverter->split($planned->uid);
            if ($result->status === ConversionStatus::Converted) {
                $split++;
            }
            $io->writeln(ConsoleTextUtility::escape(sprintf(
                'be_groups:%d %s%s: %s %s%s',
                $result->uid,
                $planned->title,
                $planned->hidden ? ' (disabled)' : '',
                $result->status->value,
                $result->message,
                $result->createdBlockUids !== [] ? ' Created: ' . implode(', ', $result->createdBlockUids) . '.' : '',
            )));
        }
        $notSplit = $skipped + count($classifications) - $split;
        if ($notSplit > 0) {
            $io->error(sprintf('%d group(s) split, %d not split, see above.', $split, $notSplit));
            return Command::FAILURE;
        }
        $io->success(sprintf('%d group(s) split. Check the result with begroups:audit.', $split));
        return Command::SUCCESS;
    }

    /**
     * @return list<int>|string the uids without duplicates, or the first argument that is no uid
     */
    private function getUids(InputInterface $input): array|string
    {
        $arguments = $input->getArgument('uids');
        $uids = [];
        foreach (is_array($arguments) ? $arguments : [] as $argument) {
            $argument = is_scalar($argument) ? (string)$argument : '';
            if (preg_match('/^[1-9]\d*$/', $argument) !== 1) {
                return $argument;
            }
            $uids[] = (int)$argument;
        }
        return array_values(array_unique($uids));
    }

    private function describeGroupThatIsNotClassic(int $uid): string
    {
        $group = $this->backendGroupRepository->findFieldsByUid($uid, ['title', 'deleted', GroupKind::FIELD_NAME]);
        if ($group === null) {
            return sprintf('be_groups:%d: skipped. The group does not exist.', $uid);
        }
        if ($group->get('deleted') !== '0') {
            return sprintf('be_groups:%d %s: skipped. The group is deleted.', $uid, $group->get('title'));
        }
        return sprintf('be_groups:%d %s: skipped. It is a group of the kind "%s", not a classic group.', $uid, $group->get('title'), $group->get(GroupKind::FIELD_NAME));
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
                $rows[] = self::wrapRow([
                    $group,
                    'new ' . $this->kindPrefix->prefixTitle($concern->kind, $classification->title),
                    // See GroupClassifier: only the page group that takes over as owner group has no permissions.
                    $concern->values === [] ? 'no permissions, owner group of new pages' : implode(', ', array_keys($concern->values)),
                ]);
                $group = '';
            }
            $rows[] = self::wrapRow([$group, 'the group becomes a role', '']);
        }
        return $rows;
    }

    /**
     * With its borders, the table is at most 120 characters wide, like the blocks of SymfonyStyle.
     *
     * @param array{string, string, string} $cells
     * @return list<string>
     */
    private static function wrapRow(array $cells): array
    {
        return [ConsoleTextUtility::wrap($cells[0], 30), ConsoleTextUtility::wrap($cells[1], 40), ConsoleTextUtility::wrap($cells[2], 40)];
    }
}
