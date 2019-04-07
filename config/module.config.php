<?php

namespace AkSearch\Module\Configuration;

$config = [
    'controllers' => [
        'factories' => [
            'AkSearch\Controller\SearchController' => 'VuFind\Controller\AbstractBaseFactory'

        ],
        'aliases' => [
            'VuFind\Controller\SearchController' => 'AkSearch\Controller\SearchController'
        ]
    ],
    'controller_plugins' => [
        'factories' => [
            'AkSearch\Controller\Plugin\NewItems' => 'AkSearch\Controller\Plugin\Factory::getNewItems'
        ],
        'aliases' => [
            'newItems' => 'AkSearch\Controller\Plugin\NewItems'
        ],
    ],
    'vufind' => [
        'plugin_managers' => [
            'ils_driver' => [
                'factories' => [
                    'AkSearch\ILS\Driver\Alma' => 'VuFind\ILS\Driver\AlmaFactory'

                ],
                'aliases' => [
                    'VuFind\ILS\Driver\Alma' => 'AkSearch\ILS\Driver\Alma'
                ]
            ],
            'search_backend' => [
                'factories' => [
                    'Solr' => 'AkSearch\Search\Factory\SolrDefaultBackendFactory'
                ],
            ],
            'search_facetcache' => [
                'factories' => [
                    'AkSearch\Search\Solr\FacetCache' => 'AkSearch\Search\Solr\FacetCacheFactory'
                ],
                'aliases' => [
                    'VuFind\Search\Solr\FacetCache' => 'AkSearch\Search\Solr\FacetCache'
                ]
            ],
            'search_results' => [
                'factories' => [
                    'AkSearch\Search\Solr\Results' => 'AkSearch\Search\Solr\ResultsFactory'
                ],
                'aliases' => [
                    'VuFind\Search\Solr\Results' => 'AkSearch\Search\Solr\Results'
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
        ]
    ]
];

return $config;
