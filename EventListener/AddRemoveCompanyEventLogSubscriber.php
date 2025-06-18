<?php

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\EventListener;

use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\LeadBundle\Entity\Company;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanyEventLog;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegment;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Event\CompanySegmentAddEvent;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Event\CompanySegmentRemoveEvent;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanyEventLogModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AddRemoveCompanyEventLogSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private CompanyEventLogModel $companyEventLogModel,
        private UserHelper $userHelper,
    ) {
        // Constructor logic if needed
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CompanySegmentAddEvent::class  => [
                ['onAddCompanySegmentEvent', 0],
            ],
            CompanySegmentRemoveEvent::class  => [
                ['onRemoveCompanySegmentEvent', 0],
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
        $companyEventLog->setBundle('company');
        $companyEventLog->setAction($action);
        $companyEventLog->setObject('company_segment');
        $companyEventLog->setObjectId($companySegment->getId());
        $companyEventLog->setDateAdded(new \DateTime());
        $userId      = null; // Set the user ID if available
        $userName    = 'System'; // or use the actual user name if available
        $currentUser = $this->userHelper->getUser();
        if ($currentUser) {
            $userId   = $currentUser->getId();
            $userName = $currentUser->getUsername();
        }
        $companyEventLog->setProperties([
            'company_segment_id'   => $companySegment->getId(),
            'company_segment_name' => $companySegment->getName(),
            'company_id'           => $company->getId(),
            'object_description'   => $companySegment->getName(),
        ]);
        $companyEventLog->setUserId($userId); // Set the user ID if available
        $companyEventLog->setUserName($userName); // or use the actual user name if available
        $this->companyEventLogModel->getRepository()->saveEntity($companyEventLog);
    }
}
