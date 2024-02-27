<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model;

use Mautic\CoreBundle\Model\FormModel;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegment;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegmentRepository;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Event\CompanySegmentEvents;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @extends FormModel<CompanySegment>
 */
class CompanySegmentModel extends FormModel
{
    public function getRepository(): CompanySegmentRepository
    {
        $repository = parent::getRepository();
        \assert($repository instanceof CompanySegmentRepository);

        return $repository;
    }

    /**
     * @param CompanySegment $entity
     * @param bool           $unlock
     */
    public function saveEntity($entity, $unlock = true): void
    {
        $isNew = null === $entity->getId();

        // set some defaults
        $this->setTimestamps($entity, $isNew, $unlock);

        $alias = $entity->getAlias();
        $alias = $this->cleanAlias($alias, '', 0, '-');

        // make sure alias is not already taken
        $repo      = $this->getRepository();
        $testAlias = $alias;
        $existing  = $repo->getSegments(null, $testAlias, $entity->getId());
        $count     = count($existing);
        $aliasTag  = $count;

        while ($count > 0) {
            $testAlias = $alias.$aliasTag;
            $existing  = $repo->getSegments(null, $testAlias, $entity->getId());
            $count     = count($existing);
            ++$aliasTag;
        }
        if ($testAlias !== $alias) {
            $alias = $testAlias;
        }
        $entity->setAlias($alias);

        $this->dispatchEvent('pre_save', $entity, $isNew);
        $repo->saveEntity($entity);
        $this->dispatchEvent('post_save', $entity, $isNew);
    }

    protected function dispatchEvent($action, &$entity, $isNew = false, ?Event $event = null): ?Event
    {
        if (!$entity instanceof CompanySegment) {
            throw new MethodNotAllowedHttpException(['CompanySegment'], 'Entity must be of class CompanySegment()');
        }

        switch ($action) {
            case 'pre_save':
                $eventClass = CompanySegmentEvents::COMPANY_SEGMENT_PRE_SAVE;
                break;
            case 'post_save':
                $eventClass = CompanySegmentEvents::COMPANY_SEGMENT_POST_SAVE;
                break;
            case 'pre_delete':
                $eventClass = CompanySegmentEvents::COMPANY_SEGMENT_PRE_DELETE;
                break;
            case 'post_delete':
                $eventClass = CompanySegmentEvents::COMPANY_SEGMENT_POST_DELETE;
                break;
            case 'pre_unpublish':
                $eventClass = CompanySegmentEvents::COMPANY_SEGMENT_PRE_UNPUBLISH;
                break;
            default:
                $eventClass = null;
        }

        if (null === $eventClass && null === $event) {
            throw new \RuntimeException('Either the Event or proper action should be provided.');
        }

        if ($this->dispatcher->hasListeners($eventClass ?? $event::class)) {
            if (null === $event) {
                if (!class_exists($eventClass)) {
                    throw new \RuntimeException('The class '.$eventClass.' does not exist.');
                }

                if (in_array($eventClass, [CompanySegmentEvents::COMPANY_SEGMENT_PRE_SAVE, CompanySegmentEvents::COMPANY_SEGMENT_POST_SAVE], true)) {
                    $event = new $eventClass($entity, $this->em, $isNew);
                } else {
                    $event = new $eventClass($entity, $this->em);
                }
            }
            $this->dispatcher->dispatch($event);

            return $event;
        }

        return null;
    }
}
