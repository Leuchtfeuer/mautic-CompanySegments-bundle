<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\EventListener;

use Mautic\CampaignBundle\CampaignEvents;
use Mautic\CampaignBundle\Event\CampaignBuilderEvent;
use Mautic\CampaignBundle\Event\CampaignExecutionEvent;
use Mautic\LeadBundle\Model\CompanyModel;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Form\Type\CampaignEventCompanySegmentsType;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Form\Type\CompanySegmentActionType;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Integration\Config;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\LeuchtfeuerCompanySegmentsEvents;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanySegmentModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CampaignSubscriber implements EventSubscriberInterface
{
    public const MANAGE_COMPANY_SEGMENT_ACTION    = 'company_segments.action.modify';
    public const MANAGE_COMPANY_SEGMENT_CONDITION = 'company_segments.condition.modify';

    public function __construct(
        private Config $config,
        private CompanySegmentModel $companySegmentModel,
        private CompanyModel $companyModel,
    ) {
    }

    public function onCampaignBuild(CampaignBuilderEvent $event): void
    {
        if (!$this->config->isPublished()) {
            return;
        }

        $action = [
            'label'           => 'plugin.company_segments.modify_contact_segment.label',
            'description'     => 'plugin.company_segments.modify_contact_segment.description',
            'formType'        => CompanySegmentActionType::class,
            'eventName'       => LeuchtfeuerCompanySegmentsEvents::MANAGE_COMPANY_SEGMENT_EVENT,
        ];

        $event->addAction(self::MANAGE_COMPANY_SEGMENT_ACTION, $action);

        $trigger = [
            'label'       => 'mautic.company_segments.events.segments',
            'description' => 'mautic.company_segments.events.segments_descr',
            'formType'    => CampaignEventCompanySegmentsType::class,
            'eventName'   => LeuchtfeuerCompanySegmentsEvents::ON_CAMPAIGN_TRIGGER_CONDITION,
        ];

        $event->addCondition(self::MANAGE_COMPANY_SEGMENT_CONDITION, $trigger);
    }

    /** @phpstan-ignore-next-line */
    public function onCampaignActionTriggerAction(CampaignExecutionEvent $event): CampaignExecutionEvent
    {
        $somethingHappened = false;

        if (!$this->config->isPublished() || !$event->checkContext(self::MANAGE_COMPANY_SEGMENT_ACTION)) {
            return $event->setResult(false);
        }

        $addTo      = $event->getConfig()['addToLists'];
        $removeFrom = $event->getConfig()['removeFromLists'];

        $lead              = $event->getLead();

        $primaryCompany    = $lead->getPrimaryCompany();

        if (null === $primaryCompany || '' === $primaryCompany || 0 === $lead->getId()) {
            return $event->setResult(true);
        }

        if ([] !== $addTo && is_array($primaryCompany) && array_key_exists('id', $primaryCompany) && '' !== $primaryCompany['id'] && null !== $primaryCompany['id']) {
            $somethingHappened = $this->addRemoveCompanyToSegment($addTo, (int) $primaryCompany['id'], true);
        }

        if ([] !== $removeFrom && is_array($primaryCompany) && array_key_exists('id', $primaryCompany) && '' !== $primaryCompany['id'] && null !== $primaryCompany['id']) {
            $somethingHappened = $this->addRemoveCompanyToSegment($removeFrom, (int) $primaryCompany['id'], false);
        }

        return $event->setResult($somethingHappened);
    }

    /**
     * @param array<int> $companySegmentIds
     */
    private function addRemoveCompanyToSegment(array $companySegmentIds, int $idPrimaryCompany, bool $isToAdd = true): bool
    {
        $somethingHappened = false;
        if ([] !== $companySegmentIds) {
            $companyEntity = $this->companyModel->getRepository()->find($idPrimaryCompany);
            if (null === $companyEntity) {
                return $somethingHappened;
            }
            if (!$isToAdd) {
                $this->companySegmentModel->removeCompany($companyEntity, $companySegmentIds, false, true);
            } else {
                $this->companySegmentModel->addCompany($companyEntity, $companySegmentIds);
            }
            $somethingHappened = true;
        }

        return $somethingHappened;
    }

    /** @phpstan-ignore-next-line */
    public function onCampaignConditionTriggerAction(CampaignExecutionEvent $event): CampaignExecutionEvent
    {
        if (!$this->config->isPublished() || !$event->checkContext(self::MANAGE_COMPANY_SEGMENT_CONDITION)) {
            return $event->setResult(false);
        }

        $companySegmentIds = $event->getConfig()['companySegments'];

        $lead           = $event->getLead();
        $primaryCompany = $lead->getPrimaryCompany();

        if (
            [] === $companySegmentIds
            || [] === $primaryCompany
            || !is_array($primaryCompany)
            || !array_key_exists('id', $primaryCompany)
            || '' === $primaryCompany['id']
            || null === $primaryCompany['id']
            || 0 === $lead->getId()
        ) {
            return $event->setResult(false);
        }

        $company = $this->companyModel->getRepository()->find($primaryCompany['id']);

        $companySegment = $this->companySegmentModel->getCompaniesSegmentsRepository()->findBy(
            [
                'company'        => $company,
                'companySegment' => $companySegmentIds,
            ]
        );

        if (is_array($companySegment) && count($companySegment) > 0) {
            return $event->setResult(true);
        }

        return $event->setResult(false);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CampaignEvents::CAMPAIGN_ON_BUILD                               => ['onCampaignBuild', 0],
            LeuchtfeuerCompanySegmentsEvents::MANAGE_COMPANY_SEGMENT_EVENT  => ['onCampaignActionTriggerAction', 0],
            LeuchtfeuerCompanySegmentsEvents::ON_CAMPAIGN_TRIGGER_CONDITION => ['onCampaignConditionTriggerAction', 0],
        ];
    }
}
