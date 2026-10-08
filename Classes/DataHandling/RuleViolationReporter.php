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

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;

/**
 * Reports every correction or rejection of the group kind rules.
 *
 * Each report is written to the system log (Administration > Log) as audit trail
 * and shown to the editing user as flash message in backend requests.
 *
 * @internal
 */
final readonly class RuleViolationReporter
{
    /**
     * sys_log type for extensions, as documented in BackendUserAuthentication::writelog().
     */
    private const LOG_TYPE_EXTENSION = 4;
    private const LOG_ACTION_NONE = 0;
    private const LOG_MESSAGE = 0;
    private const LOG_ERROR_USER = 1;
    private const LABEL_DOMAIN = 'be_groups.messages';

    public function __construct(
        private FlashMessageService $flashMessageService,
        private LanguageServiceFactory $languageServiceFactory,
    ) {}

    /**
     * The rules did what the kind demands, e.g. emptied the fields of the former kind after the kind
     * was changed. This is no error.
     *
     * @param array<string, string> $arguments
     */
    public function reportInformation(BackendUserAuthentication $backendUser, string $table, string|int $id, string $labelKey, string $logMessage, array $arguments): void
    {
        $this->report($backendUser, $table, $id, $labelKey, $logMessage, $arguments, self::LOG_MESSAGE, ContextualFeedbackSeverity::INFO);
    }

    /**
     * A value that is not allowed was not saved, e.g. a building block assigned to a user directly.
     *
     * @param array<string, string> $arguments
     */
    public function reportCorrection(BackendUserAuthentication $backendUser, string $table, string|int $id, string $labelKey, string $logMessage, array $arguments): void
    {
        $this->report($backendUser, $table, $id, $labelKey, $logMessage, $arguments, self::LOG_ERROR_USER, ContextualFeedbackSeverity::WARNING);
    }

    /**
     * A change was rejected completely.
     *
     * @param array<string, string> $arguments
     */
    public function reportRejection(BackendUserAuthentication $backendUser, string $table, string|int $id, string $labelKey, string $logMessage, array $arguments): void
    {
        $this->report($backendUser, $table, $id, $labelKey, $logMessage, $arguments, self::LOG_ERROR_USER, ContextualFeedbackSeverity::ERROR);
    }

    /**
     * @param array<string, string> $arguments
     */
    private function report(
        BackendUserAuthentication $backendUser,
        string $table,
        string|int $id,
        string $labelKey,
        string $logMessage,
        array $arguments,
        int $logError,
        ContextualFeedbackSeverity $severity,
    ): void {
        $recordUid = is_int($id) ? $id : (ctype_digit($id) ? (int)$id : 0);
        $backendUser->writelog(
            self::LOG_TYPE_EXTENSION,
            self::LOG_ACTION_NONE,
            $logError,
            null,
            $logMessage,
            $arguments + ['table' => $table, 'uid' => (string)$id],
            $table,
            $recordUid,
        );

        if (Environment::isCli()) {
            return;
        }
        $languageService = $this->languageServiceFactory->createFromUserPreferences($backendUser);
        $message = (string)$languageService->translate($labelKey . '.message', self::LABEL_DOMAIN, array_values($arguments), $logMessage);
        $title = (string)$languageService->translate($labelKey . '.title', self::LABEL_DOMAIN, [], 'be_groups');
        $this->flashMessageService
            ->getMessageQueueByIdentifier()
            ->enqueue(new FlashMessage($message, $title, $severity, true));
    }
}
