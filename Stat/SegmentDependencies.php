<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Stat;

use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanySegmentModel;

class SegmentDependencies
{
    public function __construct(private CompanySegmentModel $companySegmentModel)
    {
    }

    /**
     * @return array<int, array{'label': string, 'route': string, 'ids': array<int, string>}>
     */
    public function getChannelsIds(int $segmentId): array
    {
        $usage = [];

        $usage[] = [
            'label' => 'mautic.lead.lead.lists',
            'route' => 'mautic_segment_index',
            'ids'   => $this->companySegmentModel->getSegmentsWithDependenciesOnSegment($segmentId, 'id'),
        ];

        return $usage;
    }
}
