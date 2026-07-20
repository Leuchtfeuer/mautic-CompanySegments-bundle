<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\EventListener;

use Mautic\CoreBundle\Event\TokenReplacementEvent;
use Mautic\EmailBundle\EmailEvents;
use Mautic\EmailBundle\Event\EmailSendEvent;
use Mautic\LeadBundle\Entity\CompanyLeadRepository;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Exception\PrimaryCompanyNotFoundException;
use Mautic\LeadBundle\Helper\PrimaryCompanyHelper;
use Mautic\LeadBundle\Segment\OperatorOptions;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegmentRepository;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Integration\Config;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Evaluates company_segments filters in email Dynamic Content.
 *
 * EmailBundle bypasses ON_CONTACTS_FILTER_EVALUATE, so this subscriber intercepts
 * TOKEN_REPLACEMENT at priority -200 (before TokenSubscriber at -254) and handles
 * DC tokens with company_segments filters directly, then removes them from the
 * clickthrough so TokenSubscriber does not process them again.
 */
class EmailDynamicContentSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private CompanySegmentRepository $companySegmentRepository,
        private CompanyLeadRepository $companyLeadRepository,
        private Config $config,
        private PrimaryCompanyHelper $primaryCompanyHelper,
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EmailEvents::TOKEN_REPLACEMENT => ['onTokenReplacement', -200],
        ];
    }

    public function onTokenReplacement(TokenReplacementEvent $event): void
    {
        if (!$this->config->isPublished()) {
            return;
        }

        $clickthrough = $event->getClickthrough();
        if (!isset($clickthrough['dynamicContent']) || !is_array($clickthrough['dynamicContent'])) {
            return;
        }

        $lead = $event->getLead();
        if (null === $lead) {
            return;
        }

        if ($lead instanceof Lead) {
            $leadArray = $this->primaryCompanyHelper->getProfileFieldsWithPrimaryCompany($lead);
        } else {
            \assert(is_array($lead) && isset($lead['id']));
            $leadArray = $this->primaryCompanyHelper->mergePrimaryCompanyWithProfileFields((int) $lead['id'], $lead);
        }

        if (!isset($leadArray['id'])) {
            // Preview mode with faked data
            return;
        }

        $remainingDynamicContent = [];

        foreach ($clickthrough['dynamicContent'] as $data) {
            if (!$this->hasCompanySegmentsFilter($data['filters'] ?? [])) {
                $remainingDynamicContent[] = $data;
                continue;
            }

            $filterContent = $data['content'];

            foreach ($data['filters'] as $filter) {
                if ($this->matchFilterGroupForLead($filter['filters'] ?? [], $leadArray)) {
                    $filterContent = $filter['content'];
                    break;
                }
            }

            // Dispatch EMAIL_ON_DISPLAY so contact-field tokens in the DC content are replaced,
            // mirroring what TokenSubscriber does for DC items it handles.
            $emailSendEvent = new EmailSendEvent(
                null,
                [
                    'content' => $filterContent,
                    'email'   => $event->getPassthrough(),
                    'idHash'  => $clickthrough['idHash'] ?? null,
                    'tokens'  => $clickthrough['tokens'] ?? [],
                    'lead'    => $leadArray,
                ],
                true
            );
            $this->dispatcher->dispatch($emailSendEvent, EmailEvents::EMAIL_ON_DISPLAY);

            $event->addToken(
                '{dynamiccontent="'.$data['tokenName'].'"}',
                $emailSendEvent->getContent(!$event->isInternalSend())
            );
        }

        $clickthrough['dynamicContent'] = $remainingDynamicContent;
        $event->setClickthrough($clickthrough);
    }

    /** @param array<array<string, mixed>> $filters */
    private function hasCompanySegmentsFilter(array $filters): bool
    {
        foreach ($filters as $filter) {
            /** @var array<array<string, mixed>> $subFilters */
            $subFilters = $filter['filters'] ?? [];
            foreach ($subFilters as $condition) {
                if ('company_segments' === ($condition['type'] ?? null)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param array<array<string, mixed>> $conditions
     * @param array{id: int|string}       $lead
     */
    private function matchFilterGroupForLead(array $conditions, array $lead): bool
    {
        foreach ($conditions as $condition) {
            if ('company_segments' === ($condition['type'] ?? null)) {
                return $this->evaluateCompanySegmentsCondition($condition, $lead);
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $condition
     * @param array{id: int|string} $lead
     */
    private function evaluateCompanySegmentsCondition(array $condition, array $lead): bool
    {
        $operator    = $condition['operator'] ?? '';
        $filterValue = $condition['filter'] ?? [];

        if (!is_array($filterValue)) {
            $filterValue = [$filterValue];
        }

        $segmentIds = array_map('intval', array_filter($filterValue));

        try {
            $primaryCompany = $this->companyLeadRepository->getPrimaryCompanyByLeadId((int) $lead['id']);
            \assert(isset($primaryCompany['id']) && is_numeric($primaryCompany['id']));
            $companyId = (int) $primaryCompany['id'];
        } catch (PrimaryCompanyNotFoundException) {
            return OperatorOptions::EMPTY === $operator;
        }

        return match ($operator) {
            OperatorOptions::EMPTY     => !$this->companySegmentRepository->isCompanyInAnySegment($companyId),
            OperatorOptions::NOT_EMPTY => $this->companySegmentRepository->isCompanyInAnySegment($companyId),
            OperatorOptions::IN        => $this->companySegmentRepository->isCompanyInSegments($companyId, $segmentIds),
            OperatorOptions::NOT_IN    => $this->companySegmentRepository->isNotCompanyInSegments($companyId, $segmentIds),
            default                    => false,
        };
    }
}
