<?php

use Cretection\BeGroups\Controller\OverviewController;
use Cretection\BeGroups\Domain\Overview\OverviewBuilder;

/**
 * Definitions for modules provided by EXT:be_groups
 */
return [
    OverviewController::MODULE_IDENTIFIER => [
        'parent' => 'admin',
        'position' => ['after' => 'backend_user_management'],
        'access' => 'admin',
        'workspaces' => 'live',
        'path' => '/module/users/roles',
        'iconIdentifier' => 'module-begroups-roles',
        'labels' => 'be_groups.modules.overview',
        'routes' => [
            '_default' => [
                'target' => OverviewController::class . '::indexAction',
            ],
        ],
        'moduleData' => [
            'sorting' => OverviewBuilder::SORT_TITLE,
            'kind' => '',
        ],
    ],
];
