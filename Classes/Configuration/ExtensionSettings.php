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

namespace Cretection\BeGroups\Configuration;

use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

/**
 * Typed access to the extension configuration of be_groups.
 *
 * @internal
 */
final readonly class ExtensionSettings
{
    public const EXTENSION_KEY = 'be_groups';

    public function __construct(
        private ExtensionConfiguration $extensionConfiguration,
    ) {}

    /**
     * Whether the option "onlyShowMetaGroup" of be_groups 0.0.x is still enabled in the configuration.
     */
    public function isLegacyOnlyShowMetaGroupEnabled(): bool
    {
        try {
            $value = $this->extensionConfiguration->get(self::EXTENSION_KEY, 'onlyShowMetaGroup');
        } catch (ExtensionConfigurationExtensionNotConfiguredException|ExtensionConfigurationPathDoesNotExistException) {
            return false;
        }
        return in_array($value, [true, 1, '1'], true);
    }

    /**
     * Whether groups of kind "classic" may be created and assigned to users.
     * Enabled unless explicitly switched off, so that installing the extension never locks anybody out.
     */
    public function isClassicGroupsAllowed(): bool
    {
        try {
            $value = $this->extensionConfiguration->get(self::EXTENSION_KEY, 'allowClassicGroups');
        } catch (ExtensionConfigurationExtensionNotConfiguredException|ExtensionConfigurationPathDoesNotExistException) {
            return true;
        }
        return !in_array($value, [false, 0, '0', ''], true);
    }
}
