<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Model\FormModel;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Translation\Translator;
use Mautic\LeadBundle\Entity\OperatorListTrait;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegment;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegmentRepository;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Event\CompanySegmentEvents;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Event\CompanySegmentFiltersChoicesEvent;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Form\Type\CompanySegmentType;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Helper\SegmentCountCacheHelper;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @extends FormModel<CompanySegment>
 */
class CompanySegmentModel extends FormModel
{
    use OperatorListTrait;
    public const PROPERTIES_FIELD = CompanySegment::TABLE_NAME;

    /**
     * @var array<array<array<mixed>>>
     */
    private array $choiceFieldsCache = [];

    public function __construct(EntityManagerInterface $em, CorePermissions $security, EventDispatcherInterface $dispatcher, UrlGeneratorInterface $router, Translator $translator, UserHelper $userHelper, LoggerInterface $logger, CoreParametersHelper $coreParametersHelper, private SegmentCountCacheHelper $segmentCountCacheHelper, private RequestStack $requestStack)
    {
        parent::__construct($em, $security, $dispatcher, $router, $translator, $userHelper, $logger, $coreParametersHelper);
    }

    public function getRepository(): CompanySegmentRepository
    {
        $repository = $this->em->getRepository(CompanySegment::class);
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

    /**
     * @return array<array<array<mixed>>>
     */
    public function getChoiceFields(): array
    {
        if ([] !== $this->choiceFieldsCache) {
            return $this->choiceFieldsCache;
        }

        $choices = [];

        // Add custom choices
        if ($this->dispatcher->hasListeners(CompanySegmentEvents::SEGMENT_FILTERS_CHOICES_ON_GENERATE)) {
            $operatorsForFieldType = $this->getOperatorsForFieldType();

            $event = new CompanySegmentFiltersChoicesEvent([], $operatorsForFieldType, $this->translator, $this->requestStack->getCurrentRequest());
            $this->dispatcher->dispatch($event);
            $choices = $event->getChoices();
        }

        // Order choices by label.
        foreach ($choices as $key => $choice) {
            $cmp = static fn ($a, $b): int => strcmp($a['label'], $b['label']);
            uasort($choice, $cmp);
            $choices[$key] = $choice;
        }

        $this->choiceFieldsCache = $choices;

        return $choices;
    }

    protected function dispatchEvent($action, &$entity, $isNew = false, ?Event $event = null): ?Event
    {
        if (!$entity instanceof CompanySegment) {
            throw new MethodNotAllowedHttpException(['CompanySegment'], 'Entity must be of class CompanySegment()');
        }

        $eventClass = match ($action) {
            'pre_save'      => CompanySegmentEvents::COMPANY_SEGMENT_PRE_SAVE,
            'post_save'     => CompanySegmentEvents::COMPANY_SEGMENT_POST_SAVE,
            'pre_delete'    => CompanySegmentEvents::COMPANY_SEGMENT_PRE_DELETE,
            'post_delete'   => CompanySegmentEvents::COMPANY_SEGMENT_POST_DELETE,
            'pre_unpublish' => CompanySegmentEvents::COMPANY_SEGMENT_PRE_UNPUBLISH,
            default         => null,
        };

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

    /**
     * @param array<int> $companyIds
     *
     * @return array<int>
     *
     * @throws \Exception
     */
    public function getSegmentCompanyCountFromCache(array $companyIds): array
    {
        $companyCounts = [];

        foreach ($companyIds as $segmentId) {
            $companyCounts[$segmentId] = $this->segmentCountCacheHelper->getSegmentCompanyCount($segmentId);
        }

        return $companyCounts;
    }

    /**
     * @param array<mixed> $options
     */
    public function createForm($entity, FormFactoryInterface $formFactory, $action = null, $options = []): FormInterface
    {
        if (!$entity instanceof CompanySegment) {
            throw new MethodNotAllowedHttpException(['CompanySegment'], 'Entity must be of class CompanySegment.');
        }

        if (null !== $action && '' !== $action) {
            $options['action'] = $action;
        }

        return $formFactory->create(CompanySegmentType::class, $entity, $options);
    }

    /**
     * @return array<array{id: string, name: string, alias: string}>
     */
    public function getCompanySegments(string $alias = ''): array
    {
        $user = false === $this->security->isGranted($this->getPermissionBase().':viewother') ? $this->userHelper->getUser() : null;

        return $this->getRepository()->getSegments($user, $alias);
    }

    /**
     * @return array<int, string>
     */
    public function getSegmentsWithDependenciesOnSegment(int $segmentId, string $returnProperty = 'name'): array
    {
        $tableAlias = $this->getRepository()->getTableAlias();

        $filter = [
            'force'  => [
                ['column' => $tableAlias.'.filters', 'expr' => 'LIKE', 'value'=> '%"type": "company_segments"%'],
                ['column' => $tableAlias.'.id', 'expr' => 'neq', 'value' => $segmentId],
            ],
        ];
        $entities = $this->getEntities(
            [
                'filter' => $filter,
            ]
        );
        $dependents = [];
        $accessor   = PropertyAccess::createPropertyAccessor();
        foreach ($entities as $entity) {
            $retrFilters = $entity->getFilters();
            foreach ($retrFilters as $eachFilter) {
                $filter = $eachFilter['properties']['filter'];
                if ($filter && self::PROPERTIES_FIELD === $eachFilter['type'] && in_array($segmentId, $filter, true)) {
                    $value = $accessor->getValue($entity, $returnProperty);
                    if (!is_string($value)) {
                        continue; // Return property does not exist.
                    }

                    $dependents[] = $value;
                    break;
                }
            }
        }

        return $dependents;
    }

    /**
     * @param array<int> $segmentIds
     *
     * @return array<string>
     */
    public function canNotBeDeleted(array $segmentIds): array
    {
        $tableAlias = $this->getRepository()->getTableAlias();

        $entities = $this->getEntities(
            [
                'filter' => [
                    'force'  => [
                        ['column' => $tableAlias.'.filters', 'expr' => 'LIKE', 'value'=> '%"type": "company_segments"%'],
                    ],
                ],
            ]
        );

        $idsNotToBeDeleted   = [];
        $namesNotToBeDeleted = [];
        $dependency          = [];

        foreach ($entities as $entity) {
            $retrFilters = $entity->getFilters();
            foreach ($retrFilters as $eachFilter) {
                if (self::PROPERTIES_FIELD !== $eachFilter['type']) {
                    continue;
                }

                /** @var array<int> $filterValue */
                $filterValue       = $eachFilter['properties']['filter'];
                $idsNotToBeDeleted = array_unique(array_merge($idsNotToBeDeleted, $filterValue));
                foreach ($filterValue as $val) {
                    $dependency[$val] = array_merge($dependency[$val], [$entity->getId()]);
                    $dependency[$val] = array_unique($dependency[$val]);
                }
            }
        }
        foreach ($dependency as $key => $value) {
            if (array_intersect($value, $segmentIds) === $value) {
                $idsNotToBeDeleted = array_unique(array_diff($idsNotToBeDeleted, [$key]));
            }
        }

        $idsNotToBeDeleted = array_intersect($segmentIds, $idsNotToBeDeleted);

        foreach ($idsNotToBeDeleted as $val) {
            $notToBeDeletedEntity = $this->getEntity($val);

            if (!$notToBeDeletedEntity instanceof CompanySegment) {
                continue;
            }

            $name = $notToBeDeletedEntity->getName();

            if (null === $name) {
                continue;
            }

            $namesNotToBeDeleted[$val] = $name;
        }

        return $namesNotToBeDeleted;
    }

    public function getPermissionBase(): string
    {
        return 'lead:lists';
    }
}
