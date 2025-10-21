<?php

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\EventListener;

use Mautic\LeadBundle\Entity\CompanyLead;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\LeadBundle\Event\LeadEvent;
use Mautic\LeadBundle\Event\LeadListMergeFiltersEvent;
use Mautic\LeadBundle\LeadEvents;
use Mautic\LeadBundle\Model\CompanyModel;
use Mautic\LeadBundle\Model\LeadModel;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegment;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanySegmentModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompaniesSegments;

class CompanySegmentContactSegmentFilterSubscriber implements EventSubscriberInterface
{

    public function __construct(
        private LeadModel $leadModel,
        private CompanyModel $companyModel,
        private CompanySegmentModel $companySegmentModel
    )
    {
    }

    public static function getSubscribedEvents(){
        return [
            LeadEvents::LIST_FILTERS_MERGE => [
                'addCompanySegmentByContactSegment',
            ]
        ];
    }

    public function addCompanySegmentByContactSegment(LeadListMergeFiltersEvent $event)
    {
        $filters = $event->getFilters();
        $companySegmentIds = [];
        foreach ($filters as $keyFilter => $filter) {
            dump($filter);
            if(
                $filter['type'] !== 'leadList'
                && $filter['field'] !== 'leadList'
                && $filter['object'] !== 'companies'
            ) {
                dump('continue');
                continue;
            }
            $tempFilter = $filter['properties']['filter'];
            $leadLists = $this->leadModel->getLeadListRepository()->getEntities($tempFilter);
            foreach ($leadLists as $leadList) {
                assert($leadList instanceof LeadList);
                $leads = $leadList->getLeads();
                foreach ($leads as $lead) {
                    $currentLead  = $lead->getLead();
                    assert($currentLead instanceof Lead);
                    $companiesLeads = $this->companyModel->getCompanyLeadRepository()->findBy(
                        [
                            'lead' => $currentLead,
                            'primary' => true
                        ]
                    );
                    if (count($companiesLeads) === 0) {
                        continue;
                    }
                    $tempCompanies = [];
                    foreach ($companiesLeads as $companyLead) {
                        assert($companyLead instanceof CompanyLead);
                        $tempCompanies[] = $companyLead->getCompany();
                    }
                    if(count($tempCompanies) === 0) {
                        continue;
                    }
//                    $companies = $this->companyModel->getEntities($tempCompanies);
                    $companySegments = $this->companySegmentModel->getCompaniesSegmentsRepository()->findBy(
                        ['company' => $tempCompanies]
                    );

                    foreach ($companySegments as $companySegment) {
                        assert($companySegment instanceof CompaniesSegments);
                        $companySegmentIds[] = $companySegment->getCompanySegment()->getId();
                    }
                }
            }
            $filters[$keyFilter]['properties']['filter'] = $companySegmentIds;
        }
//        $event->setFilters($filters);

    }

}