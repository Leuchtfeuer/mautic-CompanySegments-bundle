<?php

return [
    'name'        => 'Company Segments by Leuchtfeuer',
    'description' => 'Provide a 2nd type of Segments which can contain Companies (and allows segment filters).',
    'version'     => '1.0.0',
    'author'      => 'Leuchtfeuer Digital Marketing GmbH',
    'routes'      => [
        'main' => [
            'mautic_company_segments_index' => [
                'path'       => '/companysegments/{page}',
                'controller' => 'MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Controller\CompanySegmentController::indexAction',
            ],
            'mautic_company_segments_action' => [
                'path'       => '/companysegments/{objectAction}/{objectId}',
                'controller' => 'MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Controller\CompanySegmentController::executeAction',
            ],
        ],
    ],
    'menu' => [
        'main' => [
            'items' => [
                'mautic.company_segments.menu.index' => [
                    'iconClass' => 'fa-pie-chart',
                    'access'    => ['lead:leads:viewown', 'lead:leads:viewother'],
                    'route'     => 'mautic_company_segments_index',
                    'priority'  => 65,
                    'checks'    => [
                        'integration' => [
                            'LeuchtfeuerCompanySegments' => [
                                'enabled' => true,
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
];
