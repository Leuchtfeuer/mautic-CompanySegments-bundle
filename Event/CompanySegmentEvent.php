<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Event;

use Doctrine\ORM\EntityManagerInterface;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegment;
use Symfony\Contracts\EventDispatcher\Event;

abstract class CompanySegmentEvent extends Event
{
    private EntityManagerInterface $entityManager;
    private CompanySegment $companySegment;

    public function __construct(CompanySegment $companySegment, EntityManagerInterface $entityManager)
    {
        $this->entityManager  = $entityManager;
        $this->companySegment = $companySegment;
    }

    public function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }

    public function getCompanySegment(): CompanySegment
    {
        return $this->companySegment;
    }
}
