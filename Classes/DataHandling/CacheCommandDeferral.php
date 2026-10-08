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

namespace Cretection\BeGroups\DataHandling;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * Holds back the cache commands of page TSconfig (TCEMAIN.clearCacheCmd) while the assistants
 * convert a group within a transaction. Flushing a cache group truncates the cache tables of the
 * database backend, which commits the open transaction implicitly on MySQL and MariaDB and locks
 * the tables on PostgreSQL. The converter runs the commands once the conversion is committed.
 *
 * Registered as DataHandler hook "clearCachePostProc".
 *
 * @internal
 */
#[Autoconfigure(public: true)]
final class CacheCommandDeferral
{
    private bool $deferring = false;

    /**
     * @var array<int|string, true>
     */
    private array $commands = [];

    public function start(): void
    {
        $this->deferring = true;
        $this->commands = [];
    }

    /**
     * @return list<int|string> the commands held back since start()
     */
    public function stop(): array
    {
        $commands = array_keys($this->commands);
        $this->deferring = false;
        $this->commands = [];
        return $commands;
    }

    /**
     * @param array<string, mixed> $parameters "clearCacheCommands" is a reference to the commands the DataHandler runs
     */
    public function holdBackCommands(array &$parameters): void
    {
        // The hook is also called after each command; only the queue of commands is held back.
        if (!$this->deferring || !is_array($parameters['clearCacheCommands'] ?? null)) {
            return;
        }
        foreach ($parameters['clearCacheCommands'] as $command) {
            if (is_int($command) || is_string($command)) {
                $this->commands[$command] = true;
            }
        }
        $parameters['clearCacheCommands'] = [];
    }
}
