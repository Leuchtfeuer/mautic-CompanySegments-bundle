<?php

return [
    'name'        => 'Company Segments by Leuchtfeuer',
    'description' => 'Provide a 2nd type of Segments which can contain Companies (and allows segment filters).',
    'version'     => '1.1.0',
    'author'      => 'Leuchtfeuer Digital Marketing GmbH',
    'routes'      => [
        'main' => [
            'mautic_company_segments_index' => [
                'path'       => '/company-segments/{page}',
                'controller' => 'MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Controller\CompanySegmentController::indexAction',
            ],
            'mautic_company_segments_action' => [
                'path'       => '/company-segments/{objectAction}/{objectId}',
                'controller' => 'MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Controller\CompanySegmentController::executeAction',
            ],
            'mautic_company_segments_batch_company_set' => [
                'path'       => '/company-segments/batch/company/set',
                'controller' => 'MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Controller\BatchSegmentController::setAction',
            ],
            'mautic_company_segments_batch_company_view' => [
                'path'       => '/company-segments/batch/company/view',
                'controller' => 'MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Controller\BatchSegmentController::indexAction',
            ],
        ],
        'api' => [
            'mautic_api_companysegments' => [
                'standard_entity' => true,
                'name'            => 'companysegments',
                'path'            => '/companysegments',
                'controller'      => MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Controller\Api\CompanySegmentApiController::class,
            ],
        ],
    ],
    'menu' => [
        'main' => [
            'items' => [
                'mautic.company_segments.menu.index' => [
                    'access'    => ['lead:leads:viewown', 'lead:leads:viewother'],
                    'route'     => 'mautic_company_segments_index',
                    'parent'    => 'mautic.companies.menu.index',
                    'priority'  => 15,
                    'checks'    => [
                        'integration' => [
                            'LeuchtfeuerCompanySegments' => [
                                'enabled' => true,
                            ],
                        ],
                    ],
                ],
                'mautic.companies.menu.sub.index' => [
                    'id'        => 'mautic.companies.menu.index',
                    'parent'    => 'mautic.companies.menu.index',
                    'route'     => 'mautic_company_index',
                    'access'    => ['lead:leads:viewother'],
                    'priority'  => 100,
                ],
            ],
        ],
    ],
];
