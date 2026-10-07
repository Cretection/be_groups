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

namespace Cretection\BeGroups\Domain\Kind;

/**
 * The group kinds shipped with be_groups.
 *
 * The values are stored in be_groups.tx_begroups_kind and are part of the public API.
 * Other extensions may add further kinds as TCA items and types; which kinds are valid
 * is decided by the KindRegistry, not by this enum.
 */
enum GroupKind: string
{
    case Role = 'role';
    case AccessControl = 'acl';
    case PageGroup = 'page_group';
    case DatabaseMount = 'db_mount';
    case FileMount = 'file_mount';
    case FileOperations = 'file_operations';
    case CategoryMount = 'category_mount';
    case Language = 'language';
    case TsConfig = 'tsconfig';
    case Workspace = 'workspace';
    case Classic = 'classic';

    public const FIELD_NAME = 'tx_begroups_kind';

    public function isBuildingBlock(): bool
    {
        return $this !== self::Role && $this !== self::Classic;
    }

    public function iconIdentifier(): string
    {
        return match ($this) {
            self::Role => 'status-user-group-backend',
            self::AccessControl => 'actions-shield',
            self::PageGroup => 'apps-pagetree-page-backend-users',
            self::DatabaseMount => 'actions-pagetree-mount',
            self::FileMount => 'apps-filetree-mount',
            self::FileOperations => 'actions-file-shield',
            self::CategoryMount => 'mimetypes-x-sys_category',
            self::Language => 'mimetypes-x-sys_language',
            self::TsConfig => 'mimetypes-text-typoscript',
            self::Workspace => 'mimetypes-x-sys_workspace',
            self::Classic => 'actions-key',
        };
    }

    /**
     * Prefix as recommended by the official TYPO3 permission guideline.
     */
    public function prefix(): string
    {
        return match ($this) {
            self::Role => 'R_',
            self::AccessControl => 'ACL_',
            self::PageGroup => 'PG_',
            self::DatabaseMount => 'DBM_',
            self::FileMount => 'FM_',
            self::FileOperations => 'FO_',
            self::CategoryMount => 'CM_',
            self::Language => 'L_',
            self::TsConfig => 'TS_',
            self::Workspace => 'WS_',
            self::Classic => '',
        };
    }
}
