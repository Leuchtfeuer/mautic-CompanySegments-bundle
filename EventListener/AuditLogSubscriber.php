<?php

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\EventListener;

use Mautic\CoreBundle\Helper\IpLookupHelper;
use Mautic\CoreBundle\Model\AuditLogModel;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Event\CompanySegmentPostDelete;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Event\CompanySegmentPostSave;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AuditLogSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private AuditLogModel $auditLogModel,
        private IpLookupHelper $ipLookupHelper,
    ) {
        // Constructor logic if needed
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CompanySegmentPostSave::class => [
                ['onSaveUpdateCompanySegmentAuditLog', 0],
            ],
            CompanySegmentPostDelete::class => [
                ['onDeleteCompanySegmentAuditLog', 0],
            ],
        ];
    }

    public function onSaveUpdateCompanySegmentAuditLog(CompanySegmentPostSave $companySegmentPostSave): void
    {
        $isNew  = $companySegmentPostSave->isNew();
        $action = $isNew ? 'added' : 'updated';
        $args   = $this->getArgsFromCompanySegmentToAuditLog($companySegmentPostSave, $action, 'company_segment');
        $this->auditLogModel->writeToLog($args);
    }

    public function onDeleteCompanySegmentAuditLog(CompanySegmentPostDelete $companySegmentPostDelete): void
    {
        $args = $this->getArgsFromCompanySegmentToAuditLog($companySegmentPostDelete, 'deleted', 'company_segment');
        $this->auditLogModel->writeToLog($args);
    }

    /**
     * @return array<string, mixed>
     */
    private function getArgsFromCompanySegmentToAuditLog(CompanySegmentPostSave|CompanySegmentPostDelete $companySegmentCrud, string $action, string $object): array
    {
        $objectId                        = $companySegmentCrud->getCompanySegment()->getId() ?? 0;
        $details                         = $companySegmentCrud->getChanges();
        $details['object_description']   = $companySegmentCrud->getCompanySegment()->getName();
        $details['company_segment_name'] = $companySegmentCrud->getCompanySegment()->getName();
        $details['company_segment_id']   = $objectId;

        return [
            'object'             => $object,
            'action'             => $action,
            'objectId'           => $objectId,
            'object_description' => $companySegmentCrud->getCompanySegment()->getName(),
            'bundle'             => 'company',
            'details'            => $details,
            'ipAddress'          => $this->ipLookupHelper->getIpAddressFromRequest(),
        ];
    }
}
