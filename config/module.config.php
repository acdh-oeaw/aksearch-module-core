<?php

namespace AkSearch\Module\Configuration;

$config = [
    'vufind' => [
        'plugin_managers' => [
            'search_results' => [
                'factories' => [
                    'AkSearch\Search\Solr\Results' => 'AkSearch\Search\Solr\ResultsFactory',
                ],
                'aliases' => [
                    'VuFind\Search\Solr\Results' => 'AkSearch\Search\Solr\Results',
                ]
            ],
            'search_facetcache' => [
                'factories' => [
                    'AkSearch\Search\Solr\FacetCache' => 'AkSearch\Search\Solr\FacetCacheFactory',
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
    ],
    // AK: Authorization configuration for the usergroup permission provider.
    'zfc_rbac' => [
        'vufind_permission_provider_manager' => [
            'factories' => [
                'AkSearch\Role\PermissionProvider\Usergroup' => 'AkSearch\Role\PermissionProvider\Factory::getUsergroup',
            ],
            'aliases' => [
                'usergroup' => 'AkSearch\Role\PermissionProvider\Usergroup',
            ]
        ],
    ],
];

return $config;
