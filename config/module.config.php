<?php

namespace AkSearch\Module\Configuration;

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
            /*'search_params' => [
                'factories' => [
                    'AkSearch\Search\Solr\Params' => 'AkSearch\Search\Solr\ParamsFactory',
                ],
                'aliases' => [
                    'VuFind\Search\Solr\Params' => 'AkSearch\Search\Solr\Params',
                ]
            ],*/
            'search_results' => [
                'factories' => [
                    'AkSearch\Search\Solr\Results' => 'VuFind\Search\Solr\ResultsFactory',
                ],
                'aliases' => [
                    'VuFind\Search\Solr\Results' => 'AkSearch\Search\Solr\Results',
                ]
            ],
            'search_facetcache' => [
                'factories' => [
                    'AkSearch\Search\Solr\FacetCache' => 'VuFind\Search\Solr\FacetCacheFactory',
                ],
                'aliases' => [
                    'VuFind\Search\Solr\FacetCache' => 'AkSearch\Search\Solr\FacetCache',
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
