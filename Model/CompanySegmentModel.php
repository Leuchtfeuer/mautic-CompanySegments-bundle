<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\DateTimeHelper;
use Mautic\CoreBundle\Helper\ProgressBarHelper;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Model\FormModel;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Translation\Translator;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\CompanyRepository;
use Mautic\LeadBundle\Entity\OperatorListTrait;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegment;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegmentRepository;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Event\CompanySegmentAddEvent;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Event\CompanySegmentRebuildAddEvent;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Event\CompanySegmentEvents;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Event\CompanySegmentFiltersChoicesEvent;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Event\CompanySegmentRemoveEvent;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Event\CompanySegmentRebuildRemoveEvent;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Event\SegmentPreProcessSegmentEvent;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Exception\FieldNotFoundException;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Exception\SegmentNotFoundException;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Exception\TableNotFoundException;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Form\Type\CompanySegmentType;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Helper\SegmentCountCacheHelper;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Service\CompanySegmentService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;
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

    public function __construct(EntityManagerInterface $em, CorePermissions $security, EventDispatcherInterface $dispatcher, UrlGeneratorInterface $router, Translator $translator, UserHelper $userHelper, LoggerInterface $logger, CoreParametersHelper $coreParametersHelper, private SegmentCountCacheHelper $segmentCountCacheHelper, private RequestStack $requestStack, private CompanySegmentService $companySegmentService)
    {
        parent::__construct($em, $security, $dispatcher, $router, $translator, $userHelper, $logger, $coreParametersHelper);
    }

    public function getRepository(): CompanySegmentRepository
    {
        $repository = $this->em->getRepository(CompanySegment::class);
        \assert($repository instanceof CompanySegmentRepository);

        return $repository;
    }

    public function getCompanyRepository(): CompanyRepository
    {
        $repository = $this->em->getRepository(Company::class);
        \assert($repository instanceof CompanyRepository);

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
                    if (isset($dependency[$val])) {
                        $dependency[$val] = array_merge($dependency[$val], [$entity->getId()]);
                        $dependency[$val] = array_unique($dependency[$val]);
                    } else {
                        $dependency[$val] = [$entity->getId()];
                    }
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

    /**
     * @param iterable<CompanySegment|int|string> $companySegments
     */
    public function addCompany(Company $company, iterable $companySegments): void
    {
        if (is_array($companySegments) && is_numeric(current($companySegments))) {
            foreach ($companySegments as $index => $segmentId) {
                \assert(is_numeric($segmentId));
                $companySegments[$index] = (int) $segmentId;
            }

            $companySegments = $this->getEntities([
                'filter' => [
                    'force' => [
                        [
                            'column' => CompanySegment::DEFAULT_ALIAS.'.id',
                            'expr'   => 'in',
                            'value'  => $companySegments,
                        ],
                    ],
                ],
            ]);
        }

        $companyChangeSegment = [];
        foreach ($companySegments as $companySegment) {
            if ($companySegment->hasCompany($company)) {
                continue;
            }

            $companySegment->addCompany($company);

            $companyChangeSegment[$companySegment->getId()] = $companySegment;
            $this->segmentCountCacheHelper->incrementSegmentCompanyCount($companySegment->getId());
        }

        foreach ($companyChangeSegment as $companySegment) {
            $event = new CompanySegmentAddEvent($company, $companySegment);
            $this->dispatcher->dispatch($event);

            unset($event);
        }

        if ([] !== $companyChangeSegment) {
            $this->getRepository()->saveEntities($companyChangeSegment);
        }
    }

    /**
     * @param iterable<CompanySegment|int> $companySegments
     */
    public function removeCompany(Company $company, iterable $companySegments): void
    {
        if (is_array($companySegments) && is_numeric(current($companySegments))) {
            foreach ($companySegments as $index => $segmentId) {
                \assert(is_numeric($segmentId));
                $companySegments[$index] = (int) $segmentId;
            }

            $companySegments = $this->getEntities([
                'filter' => [
                    'force' => [
                        [
                            'column' => CompanySegment::DEFAULT_ALIAS.'.id',
                            'expr'   => 'in',
                            'value'  => $companySegments,
                        ],
                    ],
                ],
            ]);
        }

        $companyChangeSegment = [];
        foreach ($companySegments as $companySegment) {
            if (!$companySegment->hasCompany($company)) {
                continue;
            }

            $companySegment->removeCompany($company);

            $companyChangeSegment[$companySegment->getId()] = $companySegment;
            $this->segmentCountCacheHelper->decrementSegmentCompanyCount($companySegment->getId());
        }

        foreach ($companyChangeSegment as $companySegment) {
            $event = new CompanySegmentRemoveEvent($company, $companySegment);
            $this->dispatcher->dispatch($event);

            unset($event);
        }

        if ([] !== $companyChangeSegment) {
            $this->getRepository()->saveEntities($companyChangeSegment);
        }
    }

    public function rebuildCompanySegment(CompanySegment $companySegment, int $limit = 100, ?int $max = null, ?OutputInterface $output = null): int
    {
        $segmentId = $companySegment->getId();
        \assert(null !== $segmentId);

        $dtHelper = new DateTimeHelper();

        $batchLimiters = ['dateTime' => $dtHelper->toUtcString()]; // @see \MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Service\CompanySegmentService::getNewCompanySegmentCompaniesQueryBuilder
        $list          = ['id' => $segmentId, 'filters' => $companySegment->getFilters()];

        $this->dispatcher->dispatch(new SegmentPreProcessSegmentEvent($list, false));

        try {
            // Get a count of companies to add
            $newCompaniesCount = $this->companySegmentService->getNewCompanySegmentsCompanyCount($companySegment, $batchLimiters);
        } catch (FieldNotFoundException) {
            // A field from filter does not exist anymore. Do not rebuild.
            return 0;
        } catch (SegmentNotFoundException) {
            // A segment from filter does not exist anymore. Do not rebuild.
            return 0;
        } catch (TableNotFoundException $e) {
            // Invalid filter table, filter definition is not well asset, or it is deleted.  Do not rebuild but log.
            $this->logger->error($e->getMessage());

            return 0;
        }

        // Ensure we do not fetch newer segments in each batch
        \assert(is_numeric($newCompaniesCount[$segmentId]['maxId']));
        $batchLimiters['maxId'] = (int) $newCompaniesCount[$segmentId]['maxId'];

        // Number of total companies to process
        \assert(is_numeric($newCompaniesCount[$segmentId]['count']));
        $companiesCount = (int) $newCompaniesCount[$segmentId]['count'];

        if (0 === $companiesCount) {
            $this->logger->info('Company Segment QB - No new companies for segment found.');
        }

        if (null !== $output) {
            $output->writeln($this->translator->trans('mautic.company_segments.rebuild.to_be_added', ['%companies%' => $companiesCount, '%batch%' => $limit]));
        }

        // Handle by batches
        $start = $companiesProcessed = 0;

        // Try to save some memory
        gc_enable();

        if ($companiesCount > 0) {
            $maxCount = $max > 0 ? $max : $companiesCount;

            if (null !== $output) {
                $progress = ProgressBarHelper::init($output, $maxCount);
                $progress->start();
            }

            // Add companies
            while ($start < $companiesCount) {
                // Keep CPU down for large segments; sleep per $limit batch
                $this->batchSleep();

                $this->logger->debug(sprintf('Company Segment QB - Fetching new companies for segment [%d] %s', $segmentId, $companySegment->getName()));
                $newCompanyList = $this->companySegmentService->getNewCompanySegmentCompanies($companySegment, $batchLimiters, $limit);

                if ([] === $newCompanyList[$segmentId]) {
                    // Somehow ran out of companies so break out
                    break;
                }

                $processedCompanies = [];
                $this->logger->debug(sprintf('Company Segment QB - Adding %d new companies to segment [%d] %s', count($newCompanyList[$segmentId]), $segmentId, $companySegment->getName()));
                foreach ($newCompanyList[$segmentId] as $companyProperties) {
                    \assert(is_array($companyProperties));
                    $companyId = $companyProperties['id'];
                    \assert(is_numeric($companyId));
                    $companyId = (int) $companyId;

                    $this->logger->debug(sprintf('Company Segment QB - Adding company #%s to segment [%d] %s', $companyId, $segmentId, $companySegment->getName()));

                    $company = $this->getCompanyRepository()->getEntity($companyId);
                    if (null === $company) {
                        $this->logger->info(sprintf('Company Segment QB - Can not find a company #%s to add to segment [%d] %s', $companyId, $segmentId, $companySegment->getName()));
                        continue;
                    }

                    $this->addCompany($company, [$companySegment]);
                    $processedCompanies[] = $company;

                    ++$companiesProcessed;
                    if (null !== $output && $companiesProcessed < $maxCount) {
                        $progress->setProgress($companiesProcessed);
                    }

                    if ($max > 0 && $companiesProcessed >= $max) {
                        break;
                    }
                }

                $this->logger->info(sprintf('Company Segment QB - Added %d new companies to segment [%d] %s', count($newCompanyList[$segmentId]), $segmentId, $companySegment->getName()));

                $start += $limit;

                // Dispatch batch event
                if (count($processedCompanies) > 0 && $this->dispatcher->hasListeners(CompanySegmentRebuildAddEvent::class)) {
                    $this->dispatcher->dispatch(
                        new CompanySegmentRebuildAddEvent($processedCompanies, $companySegment),
                    );
                }

                unset($newCompanyList);

                // Free some memory
                gc_collect_cycles();

                if ($max > 0 && $companiesProcessed >= $max) {
                    if (null !== $output) {
                        $progress->finish();
                        $output->writeln('');
                    }

                    return $companiesProcessed;
                }
            }

            if (null !== $output) {
                $progress->finish();
                $output->writeln('');
            }
        }

        // Unset max ID to prevent capping at newly added max ID
        unset($batchLimiters['maxId']);

        $orphanCompaniesCount = $this->companySegmentService->getOrphanedCompanySegmentCompaniesCount($companySegment);

        // Ensure the same list is used each batch
        \assert(is_numeric($orphanCompaniesCount[$segmentId]['maxId']));
        $batchLimiters['maxId'] = (int) $orphanCompaniesCount[$segmentId]['maxId'];

        // Restart batching
        $start = 0;
        \assert(is_numeric($orphanCompaniesCount[$segmentId]['count']));
        $companiesCount = (int) $orphanCompaniesCount[$segmentId]['count'];

        if (null !== $output) {
            $output->writeln($this->translator->trans('mautic.company_segments.rebuild.to_be_removed', ['%companies%' => $companiesCount, '%batch%' => $limit]));
        }

        if ($companiesCount > 0) {
            $maxCount = $max > 0 ? $max : $companiesCount;

            if (null !== $output) {
                $progress = ProgressBarHelper::init($output, $maxCount);
                $progress->start();
            }

            // Remove companies
            while ($start < $companiesCount) {
                // Keep CPU down for large lists; sleep per $limit batch
                $this->batchSleep();

                $removeCompanyList = $this->companySegmentService->getOrphanedCompanySegmentCompanies($companySegment, $batchLimiters, $limit);

                if ([] === $removeCompanyList[$segmentId]) {
                    // Somehow ran out of companies so break out
                    break;
                }

                $processedCompanies = [];
                foreach ($removeCompanyList[$segmentId] as $companyProperties) {
                    \assert(is_array($companyProperties));
                    $companyId = $companyProperties['id'];
                    \assert(is_numeric($companyId));
                    $companyId = (int) $companyId;

                    $company = $this->getCompanyRepository()->getEntity($companyId);
                    if (null === $company) {
                        $this->logger->info(sprintf('Company Segment QB - Can not find a company #%s to add to segment [%d] %s', $companyId, $segmentId, $companySegment->getName()));
                        continue;
                    }

                    $this->removeCompany($company, [$companySegment]);
                    $processedCompanies[] = $company;
                    ++$companiesProcessed;
                    if (null !== $output && isset($progress) && $companiesProcessed < $maxCount) {
                        $progress->setProgress($companiesProcessed);
                    }

                    if ($max > 0 && $companiesProcessed >= $max) {
                        break;
                    }
                }

                // Dispatch batch event
                if (count($processedCompanies) > 0 && $this->dispatcher->hasListeners(CompanySegmentRebuildRemoveEvent::class)) {
                    $this->dispatcher->dispatch(
                        new CompanySegmentRebuildRemoveEvent($processedCompanies, $companySegment),
                    );
                }

                $start += $limit;

                unset($removeCompanyList);

                // Free some memory
                gc_collect_cycles();

                if ($max > 0 && $companiesProcessed >= $max) {
                    if (null !== $output && isset($progress)) {
                        $progress->finish();
                        $output->writeln('');
                    }

                    return $companiesProcessed;
                }
            }

            if (null !== $output && isset($progress)) {
                $progress->finish();
                $output->writeln('');
            }
        }

        $totalCompaniesCount = $this->getRepository()->getCompanyCount([$segmentId]);
        $this->segmentCountCacheHelper->setSegmentCompanyCount($segmentId, $totalCompaniesCount[$segmentId] ?? 0);

        return $companiesProcessed;
    }

    /**
     * Batch sleep according to settings.
     */
    private function batchSleep(): void
    {
        $leadSleepTime = $this->coreParametersHelper->get('batch_lead_sleep_time', false);
        if (false === $leadSleepTime) {
            $leadSleepTime = $this->coreParametersHelper->get('batch_sleep_time', 1);
        }

        if (false === $leadSleepTime || '' === $leadSleepTime || !is_numeric($leadSleepTime)) {
            return;
        }

        $leadSleepTime = (int) $leadSleepTime;

        if ($leadSleepTime < 1) {
            usleep($leadSleepTime * 1_000_000);
        } else {
            sleep($leadSleepTime);
        }
    }

    public function getPermissionBase(): string
    {
        return 'lead:lists';
    }
}
