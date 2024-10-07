<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Validator\Constraints;

use Mautic\LeadBundle\Segment\OperatorOptions;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegment;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanySegmentModel;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class CircularDependencyValidator extends ConstraintValidator
{
    public function __construct(
        private CompanySegmentModel $model,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * @phpstan-param array<mixed>|mixed $filters
     */
    public function validate($filters, Constraint $constraint): void
    {
        if (!$constraint instanceof CircularDependency) {
            throw new UnexpectedTypeException($constraint, CircularDependency::class);
        }

        if (!is_array($filters)) {
            throw new UnexpectedTypeException($filters, 'array');
        }

        $dependentSegmentIds = $this->flatten(array_map(function ($id): array {
            if (!is_int($id)) {
                $id = null;
            }
            $entity = $this->model->getEntity($id);
            assert($entity instanceof CompanySegment);

            return $this->reduceToSegmentIds($entity->getFilters());
        }, $this->reduceToSegmentIds($filters)));

        try {
            $segmentId = $this->getSegmentIdFromRequest();
            if (in_array($segmentId, $dependentSegmentIds, true)) {
                $this->context->addViolation($constraint->message);
            }
        } catch (\UnexpectedValueException) {
            // Segment ID is not in the request. May be new segment.
        }
    }

    private function getSegmentIdFromRequest(): int
    {
        $request = $this->requestStack->getCurrentRequest();

        if (null === $request) {
            throw new \UnexpectedValueException('Request is null.');
        }

        $routeParams = $request->get('_route_params');

        if (!is_array($routeParams) || !isset($routeParams['objectId']) || !is_numeric($routeParams['objectId'])) {
            throw new \UnexpectedValueException('Segment ID is missing in the request');
        }

        return (int) $routeParams['objectId'];
    }

    /**
     * @param array<array<mixed>> $filters
     *
     * @return array<mixed>
     */
    private function reduceToSegmentIds(array $filters): array
    {
        $segmentFilters = array_filter($filters, static fn (array $filter): bool => CompanySegmentModel::PROPERTIES_FIELD === $filter['type']
            && in_array($filter['operator'], [OperatorOptions::IN, OperatorOptions::NOT_IN], true));

        $segmentIdsInFilter = array_map(static function (array $filter) {
            $bcValue = $filter['filter'] ?? [];

            return $filter['properties']['filter'] ?? $bcValue;
        }, $segmentFilters);

        return $this->flatten($segmentIdsInFilter);
    }

    /**
     * @param array<array<mixed>> $array
     *
     * @return array<mixed>
     */
    private function flatten(array $array): array
    {
        return array_unique(array_reduce($array, 'array_merge', []));
    }
}
