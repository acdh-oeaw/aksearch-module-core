<?php

namespace AkSearch\Module\Configuration;

$config = [
    'controllers' => [
        'factories' => [
            'AkSearch\Controller\AlmaController' => 'VuFind\Controller\AbstractBaseFactory',
            'AkSearch\Controller\InstallController' => 'VuFind\Controller\AbstractBaseFactory',
            'AkSearch\Controller\MyResearchController' => 'VuFind\Controller\AbstractBaseFactory',
            'AkSearch\Controller\SearchController' => 'VuFind\Controller\AbstractBaseFactory'
        ],
        'aliases' => [
            'VuFind\Controller\AlmaController' => 'AkSearch\Controller\AlmaController',
            'VuFind\Controller\InstallController' => 'AkSearch\Controller\InstallController',
            'VuFind\Controller\MyResearchController' => 'AkSearch\Controller\MyResearchController',
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
            'db_row' => [
                'factories' => [
                    'AkSearch\Db\Row\Loans' => 'VuFind\Db\Row\RowGatewayFactory'
                ],
                'aliases' => [
                    'loans' => 'AkSearch\Db\Row\Loans'
                ]
            ],
            'db_table' => [
                'factories' => [
                    'AkSearch\Db\Table\Loans' => 'VuFind\Db\Table\GatewayFactory'
                ],
                'aliases' => [
                    'loans' => 'AkSearch\Db\Table\Loans'
                ]
            ],
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

$staticRoutes = [
    'Install/FixLoanHistoryTable'
];

$routeGenerator = new \VuFind\Route\RouteGenerator();
$routeGenerator->addStaticRoutes($config, $staticRoutes);

return $config;
