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
    // AK: Connection to custom Alma ILS driver (\AkSearch\ILS\Driver\Alma.php)
    //     by providing an appropriate PluginManager and ConnectionFactory.
    'service_manager' => [
        'factories' => [
            'AkSearch\ILS\Driver\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
            'VuFind\ILS\Connection' => 'AkSearch\ILS\ConnectionFactory',
            //'AkSearch\Search\Params\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',
            //'AkSearch\Search\Results\PluginManager' => 'VuFind\ServiceManager\AbstractPluginManagerFactory',

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
            ]
        ]
    ]
];

return $config;
?>
