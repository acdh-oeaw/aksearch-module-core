<?php

namespace AkSearch\Module\Configuration;

// Show PHP errors:
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(-1);

$config = [
    'controllers' => [
        'factories' => [
            'AkSearch\Controller\AlmaController' => 'VuFind\Controller\AbstractBaseFactory',
            'AkSearch\Controller\AuthorController' => 'VuFind\Controller\AbstractBaseFactory',
            'AkSearch\Controller\BrowseController' => 'VuFind\Controller\AbstractBaseWithConfigFactory',
            'AkSearch\Controller\ContentController' => 'VuFind\Controller\AbstractBaseFactory',
            'AkSearch\Controller\EmailAlertController' => 'VuFind\Controller\AbstractBaseFactory',
            'AkSearch\Controller\InstallController' => 'VuFind\Controller\AbstractBaseFactory',
            'AkSearch\Controller\MyResearchController' => 'VuFind\Controller\AbstractBaseFactory',
            'AkSearch\Controller\SearchController' => 'VuFind\Controller\AbstractBaseFactory'
        ],
        'aliases' => [
            'Author' => 'AkSearch\Controller\AuthorController',
            'author' => 'AkSearch\Controller\AuthorController',
            'Content' => 'AkSearch\Controller\ContentController',
            'content' => 'AkSearch\Controller\ContentController',
            'EmailAlert' => 'AkSearch\Controller\EmailAlertController',
            'emailalert' => 'AkSearch\Controller\EmailAlertController',
            'VuFind\Controller\AlmaController' => 'AkSearch\Controller\AlmaController',
            'VuFind\Controller\BrowseController' => 'AkSearch\Controller\BrowseController',
            'VuFind\Controller\InstallController' => 'AkSearch\Controller\InstallController',
            'VuFind\Controller\MyResearchController' => 'AkSearch\Controller\MyResearchController',
            'VuFind\Controller\SearchController' => 'AkSearch\Controller\SearchController'
        ]
    ],
    'controller_plugins' => [
        'factories' => [
            'AkSearch\Controller\Plugin\NewItems' => 'AkSearch\Controller\Plugin\NewItemsFactory'
        ],
        'aliases' => [
            'newItems' => 'AkSearch\Controller\Plugin\NewItems'
        ],
    ],
    'service_manager' => [
        'allow_override' => true,
        'factories' => [
            'AkSearch\Auth\Manager' => 'VuFind\Auth\ManagerFactory',
            'AkSearch\ILS\Logic\Holds' => 'VuFind\ILS\Logic\LogicFactory',
            'AkSearch\Mailer\Mailer' => 'VuFind\Mailer\Factory',
            'AkSearch\Search\History' => 'AkSearch\Search\HistoryFactory'
        ],
        'aliases' => [
            'VuFind\Auth\Manager' => 'AkSearch\Auth\Manager',
            'VuFind\ILS\HoldLogic' => 'AkSearch\ILS\Logic\Holds',
            'VuFind\Mailer' => 'AkSearch\Mailer\Mailer',
            'VuFind\Search\History' => 'AkSearch\Search\History'
        ],
    ],
    'vufind' => [
        'plugin_managers' => [
            'ajaxhandler' => [
                'factories' => [
                    'AkSearch\AjaxHandler\GetEmailAlertData' =>
                        'AkSearch\AjaxHandler\GetEmailAlertDataFactory',
                    'AkSearch\AjaxHandler\GetEmailAlertPopupTemplate' =>
                        'AkSearch\AjaxHandler\GetEmailAlertPopupTemplateFactory',
                    'AkSearch\AjaxHandler\GetFacetData' =>
                        'AkSearch\AjaxHandler\GetFacetDataFactory',
                    'AkSearch\AjaxHandler\GetSlideFacetTemplate' =>
                        'AkSearch\AjaxHandler\GetSlideFacetTemplateFactory'
                    
                ],
                'aliases' => [
                    'getEmailAlertData' => 'AkSearch\AjaxHandler\GetEmailAlertData',
                    'getEmailAlertPopupTemplate' => 
                        'AkSearch\AjaxHandler\GetEmailAlertPopupTemplate',
                    'getFacetData' => 'AkSearch\AjaxHandler\GetFacetData',
                    'getSlideFacetTemplate' =>
                        'AkSearch\AjaxHandler\GetSlideFacetTemplate'
                ]
            ],
            'auth' => [
                'factories' => [
                    'AkSearch\Auth\AlmaDatabase' => 'VuFind\Auth\ILSFactory'
                ],
                'aliases' => [
                    'almadatabase' => 'AkSearch\Auth\AlmaDatabase'
                ]
            ],
            'captcha' => [
                'factories' => [
                    'VuFind\Captcha\Image' => 'AkSearch\Captcha\ImageFactory'
                ]
            ],
            'contentblock' => [
                'factories' => [
                    'AkSearch\ContentBlock\FacetList' => 'VuFind\ContentBlock\FacetListFactory'
                ],
                'aliases' => [
                    'facetlist' => 'AkSearch\ContentBlock\FacetList'
                ]
            ],
            'db_row' => [
                'factories' => [
                    'AkSearch\Db\Row\Loans' => 'VuFind\Db\Row\RowGatewayFactory',
                    'AkSearch\Db\Row\Search' => 'VuFind\Db\Row\RowGatewayFactory'
                ],
                'aliases' => [
                    'loans' => 'AkSearch\Db\Row\Loans',
                    'VuFind\Db\Row\Search' => 'AkSearch\Db\Row\Search'
                ]
            ],
            'db_table' => [
                'factories' => [
                    'AkSearch\Db\Table\Loans' => 'VuFind\Db\Table\GatewayFactory',
                    'AkSearch\Db\Table\Search' => 'VuFind\Db\Table\GatewayFactory'
                ],
                'aliases' => [
                    'loans' => 'AkSearch\Db\Table\Loans',
                    'VuFind\Db\Table\Search' => 'AkSearch\Db\Table\Search'
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
            'recommend' => [
                'factories' => [
                    'AkSearch\Recommend\SideFacets' => 'AkSearch\Recommend\SideFacetsFactory'
                ],
                'aliases' => [
                    'sidefacets' => 'AkSearch\Recommend\SideFacets'
                ]
            ],
            'recorddriver' => [
                'factories' => [
                    'AkSearch\RecordDriver\SolrDefault' => 'VuFind\RecordDriver\SolrDefaultFactory',
                    'AkSearch\RecordDriver\SolrMarc' => 'VuFind\RecordDriver\SolrDefaultFactory'
                ],
                'aliases' => [
                    'VuFind\RecordDriver\SolrDefault' => 'AkSearch\RecordDriver\SolrDefault',
                    'VuFind\RecordDriver\SolrMarc' => 'AkSearch\RecordDriver\SolrMarc'
                ],
                'delegators' => [
                    'AkSearch\RecordDriver\SolrMarc' => [
                        'AkSearch\RecordDriver\IlsAwareDelegatorFactory'
                    ]
                ]
            ],
            'recordtab' => [
                'factories' => [
                    'AkSearch\RecordTab\Description' => '\Laminas\ServiceManager\Factory\InvokableFactory',
                    'AkSearch\RecordTab\HoldingsILS' => 'VuFind\RecordTab\HoldingsILSFactory',
                    'AkSearch\RecordTab\Parts' => '\Laminas\ServiceManager\Factory\InvokableFactory',
                    'AkSearch\RecordTab\Provenance' => 'AkSearch\RecordTab\ProvenanceFactory',
                    'AkSearch\RecordTab\StaffViewArray' => '\Laminas\ServiceManager\Factory\InvokableFactory',
                    'AkSearch\RecordTab\StaffViewMARC' => '\Laminas\ServiceManager\Factory\InvokableFactory'
                ],
                'aliases' => [
                    'description' => 'AkSearch\RecordTab\Description',
                    'holdingsils' => 'AkSearch\RecordTab\HoldingsILS',
                    'parts' => 'AkSearch\RecordTab\Parts',
                    'provenance' => 'AkSearch\RecordTab\Provenance',
                    'staffviewarray' => 'AkSearch\RecordTab\StaffViewArray',
                    'staffviewmarc' => 'AkSearch\RecordTab\StaffViewMARC'
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
            'search_options' => [
                'factories' => [
                    'AkSearch\Search\Solr\Options' => 'VuFind\Search\OptionsFactory'
                ],
                'aliases' => [
                    'VuFind\Search\Solr\Options' => 'AkSearch\Search\Solr\Options'
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
    'lmc_rbac' => [
        'vufind_permission_provider_manager' => [
            'factories' => [
                'AkSearch\Role\PermissionProvider\Usergroup' => 'AkSearch\Role\PermissionProvider\UsergroupFactory',
            ],
            'aliases' => [
                'usergroup' => 'AkSearch\Role\PermissionProvider\Usergroup',
            ]
        ]
    ]
];

$staticRoutes = [
    'Install/FixEmailAlertInUserTable', 'Install/FixLoanHistoryTable',
    'MyResearch/ChangeUserdata', 'EmailAlert/Processor',
    'EmailAlert/CancelPopup', 'EmailAlert/Subscribe'
];

$routeGenerator = new \VuFind\Route\RouteGenerator();
$routeGenerator->addStaticRoutes($config, $staticRoutes);

return $config;
