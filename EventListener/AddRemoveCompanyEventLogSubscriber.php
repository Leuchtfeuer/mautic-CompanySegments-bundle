<?php

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\EventListener;

use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Event\CompanyEvent;
use Mautic\LeadBundle\LeadEvents;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegment;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Event\CompanySegmentAddEvent;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Event\CompanySegmentRemoveEvent;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanyEventLogModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanyEventLog;

class AddRemoveCompanyEventLogSubscriber implements EventSubscriberInterface
{

    public function __construct(
        private CompanyEventLogModel $companyEventLogModel,
    ) {
        // Constructor logic if needed
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CompanySegmentAddEvent::class  => [
                ['onAddCompanySegmentEvent', 0]
            ],
            CompanySegmentRemoveEvent::class  => [
                ['onRemoveCompanySegmentEvent', 0]
            ],
        ];
    }

    public function onAddCompanySegmentEvent(CompanySegmentAddEvent $companySegmentAddEvent): void
    {
        $this->saveCompanyEventLog(
            'added',
            $companySegmentAddEvent->getCompany(),
            $companySegmentAddEvent->getCompanySegment()
        );
    }

    public function onRemoveCompanySegmentEvent(CompanySegmentRemoveEvent $companySegmentAddEvent): void
    {
        $this->saveCompanyEventLog(
            'removed',
            $companySegmentAddEvent->getCompany(),
            $companySegmentAddEvent->getCompanySegment()
        );
    }

    private function saveCompanyEventLog(string $action, Company $company, CompanySegment $companySegment): void
    {
        $companyEventLog = new CompanyEventLog();
        $companyEventLog->setCompany($company);
        $companyEventLog->setBundle('LeuchtfeuerCompanySegments');
        $companyEventLog->setAction($action);
        $companyEventLog->setObject('company_segment');
        $companyEventLog->setObjectId($companySegment->getId());
        $companyEventLog->setDateAdded(new \DateTime());
        $companyEventLog->setUserId(null); // Set the user ID if available
        $companyEventLog->setUserName('System'); // or use the actual user name if available
        $this->companyEventLogModel->getRepository()->saveEntity($companyEventLog);
    }
}