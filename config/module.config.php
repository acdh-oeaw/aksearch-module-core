<?php

namespace AkSearch\Module\Configuration;

/*
// Show PHP errors:
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(- 1);
*/

$config = [
    // Authorization configuration:
    // AK: Using AkSearch\Role\DynamicRoleProviderFactory which in turn uses
    //     AkSearch\Role\PermissionProvider\PluginManager which defines the
    //     "usergroup" permission.
    'zfc_rbac' => [
        'role_provider_manager' => [
            'factories' => [
                'VuFind\Role\DynamicRoleProvider' => 'AkSearch\Role\DynamicRoleProviderFactory',
            ],
        ],
    ],
    'vufind' => [
        'plugin_managers' => [
            'search_params' => [
                'factories' => [
                    'AkSearch\Search\Solr\Params' => 'VuFind\Search\Solr\ParamsFactory',
                ],
                'aliases' => [
                    'VuFind\Search\Solr\Params' => 'AkSearch\Search\Solr\Params',
                ]
            ],
            'ils_driver' => [
                'factories' => [
                    'AkSearch\ILS\Driver\Alma' => 'VuFind\ILS\Driver\AlmaFactory'

                ],
                'aliases' => [
                    'VuFind\ILS\Driver\Alma' => 'AkSearch\ILS\Driver\Alma',
                ]
            ],
        ]
    ]
];

return $config;
