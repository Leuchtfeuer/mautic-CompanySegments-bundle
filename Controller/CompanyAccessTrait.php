<?php

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Controller;

use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanyEventLogModel;
use Symfony\Component\HttpFoundation\Response;

trait CompanyAccessTrait
{
    /**
     * Determines if the user has access to the company the note is for.
     *
     * @param bool $isPlugin
     *
     * @return Response|Company
     */
    protected function checkLeadAccess($companyId, $action, $isPlugin = false, $integration = '')
    {
        if (!$companyId instanceof Company) {
            // make sure the user has view access to this company
            $companyModel = $this->getModel('lead.company');
            $company      = $companyModel->getEntity((int) $companyId);
        } else {
            $company   = $companyId;
            $companyId = $company->getId();
        }

        if (null === $company || !$company->getId()) {
            if (method_exists($this, 'postActionRedirect')) {
                // set the return URL
                $page      = $this->getCurrentRequest()->getSession()->get($isPlugin ? 'mautic.'.$integration.'.page' : 'mautic.company.page', 1);
                $returnUrl = $this->generateUrl($isPlugin ? 'mautic_plugin_timeline_index' : 'mautic_contact_index', ['page' => $page]);

                return $this->postActionRedirect(
                    [
                        'returnUrl'       => $returnUrl,
                        'viewParameters'  => ['page' => $page],
                        'contentTemplate' => $isPlugin ? 'MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Controller\CompanyTimelineController::pluginIndexAction' : 'MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Controller\CompanyController::indexAction',
                        'passthroughVars' => [
                            'activeLink'    => $isPlugin ? '#mautic_plugin_timeline_index' : '#mautic_contact_index',
                            'mauticContent' => 'CompanyTimeline',
                        ],
                        'flashes' => [
                            [
                                'type'    => 'error',
                                'msg'     => 'mautic.company.copmanyeventlog.error.notfound',
                                'msgVars' => ['%id%' => $companyId],
                            ],
                        ],
                    ]
                );
            } else {
                return $this->notFound('mautic.company.error.notfound');
            }
        } elseif (!$this->security->hasEntityAccess(
            'company:companies:'.$action.'own',
            'company:companies:'.$action.'other',
            $company->getPermissionUser()
        )
        ) {
            return $this->accessDenied();
        } else {
            return $company;
        }
    }

    /**
     * Returns companies the user has access to.
     *
     * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
     */
    protected function checkAllAccess($action, $limit)
    {
        /** @var CompanyEventLogModel $model */
        $model = $this->getModel('company_segments.company_event_log');

        // make sure the user has view access to companies
        $repo = $model->getRepository();

        // order by lastactive, filter
        $companies = $repo->getEntities(
            [
                'filter' => [
                    'force' => [
                        [
                            'column' => 'l.date_identified',
                            'expr'   => 'isNotNull',
                        ],
                    ],
                ],
                'oderBy'         => 'r.last_active',
                'orderByDir'     => 'DESC',
                'limit'          => $limit,
                'hydration_mode' => 'HYDRATE_ARRAY',
            ]);

        if (null === $companies) {
            return $this->accessDenied();
        }

        foreach ($companies as $company) {
            if (!$this->security->hasEntityAccess(
                'company:companies:'.$action.'own',
                'company:companies:'.$action.'other',
                $company->getOwner()
            )
            ) {
                unset($company);
            }
        }

        return $companies;
    }
}