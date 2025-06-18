<?php

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Controller;

use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanyEventLogModel;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanyModelDecorated;
use Symfony\Component\HttpFoundation\RequestStack;

trait CompanyDetailsTrait
{
//    private ?RequestStack $requestStack = null;

    /**
     * @param int $page
     * @param int $limit
     */
    protected function getEngagements(Company $company, array $filters = null, array $orderBy = null, $page = 1, $limit = 25): array
    {
        $session = $this->requestStack->getCurrentRequest()->getSession();

        if (null == $filters) {
            $filters = $session->get(
                'mautic.company.'.$company->getId().'.timeline.filters',
                [
                    'search'        => '',
                    'includeEvents' => [],
                    'excludeEvents' => [],
                ]
            );
        }

        if (null == $orderBy) {
            if (!$session->has('mautic.company.'.$company->getId().'.timeline.orderby')) {
                $session->set('mautic.company.'.$company->getId().'.timeline.orderby', 'timestamp');
                $session->set('mautic.company.'.$company->getId().'.timeline.orderbydir', 'DESC');
            }

            $orderBy = [
                $session->get('mautic.company.'.$company->getId().'.timeline.orderby'),
                $session->get('mautic.company.'.$company->getId().'.timeline.orderbydir'),
            ];
        }
        /** @var CompanyEventLogModel $model */
        $model = $this->getModel('company_segments.company_event_log');

        return $model->getEngagements($company, $filters, $orderBy, $page, $limit);
    }

    /**
     * @param int $page
     */
    protected function getAllEngagements(array $companies, array $filters = null, array $orderBy = null, $page = 1, $limit = 25): array
    {
        $session = $this->requestStack->getCurrentRequest()->getSession();

        if (null == $filters) {
            $filters = $session->get(
                'mautic.plugin.company.timeline.filters',
                [
                    'search'        => '',
                    'includeEvents' => [],
                    'excludeEvents' => [],
                ]
            );
        }

        if (null == $orderBy) {
            if (!$session->has('mautic.plugin.company.timeline.orderby')) {
                $session->set('mautic.plugin.company.timeline.orderby', 'timestamp');
                $session->set('mautic.plugin.company.timeline.orderbydir', 'DESC');
            }

            $orderBy = [
                $session->get('mautic.plugin.company.timeline.orderby'),
                $session->get('mautic.plugin.company.timeline.orderbydir'),
            ];
        }

        // prepare result object
        $result = [
            'events'   => [],
            'filters'  => $filters,
            'order'    => $orderBy,
            'types'    => [],
            'total'    => 0,
            'page'     => $page,
            'limit'    => $limit,
            'maxPages' => 0,
        ];

        // get events for each contact
        foreach ($companies as $company) {
            //  if (!$lead->getEmail()) continue; // discard contacts without email

            /** @var CompanyEventLogModel $model */
            $model       = $this->getModel('company_segments.company_event_log');
            $engagements = $model->getEngagements($company, $filters, $orderBy, $page, $limit);
            $events      = $engagements['events'];
            $types       = $engagements['types'];

            // inject lead into events
            foreach ($events as &$event) {
                $event['leadId']    = $company->getId();
                $event['leadEmail'] = $company->getEmail();
                $event['leadName']  = $company->getName() ?: $company->getEmail();
            }

            $result['events'] = array_merge($result['events'], $events);
            $result['types']  = array_merge($result['types'], $types);
            $result['total'] += $engagements['total'];
        }

        $result['maxPages'] = ($limit <= 0) ? 1 : round(ceil($result['total'] / $limit));

        usort($result['events'], [$this, 'cmp']); // sort events by

        // now all events are merged, let's limit to   $limit
        array_splice($result['events'], $limit);

        $result['total'] = count($result['events']);

        return $result;
    }
}