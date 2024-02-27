<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\Fixtures;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CoreBundle\Entity\FormEntity;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanySegmentModel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\EventDispatcher\Event;

class CompanySegmentModelStub extends CompanySegmentModel
{
    public function testDispatchEvent(string $action, FormEntity $entity, bool $isNew = false, ?Event $event = null): ?Event
    {
        return $this->dispatchEvent($action, $entity, $isNew, $event);
    }

    public function setDispatcher(EventDispatcherInterface $dispatcher): void
    {
        $this->dispatcher = $dispatcher;
    }

    public function setEntityManger(EntityManagerInterface $em): void
    {
        $this->em = $em;
    }
}
