<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\DTO;

use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Mautic\ApiBundle\Serializer\Driver\ApiMetadataDriver;
use Mautic\CategoryBundle\Entity\Category;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadList;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegment;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class CompanySegmentAsLeadSegment extends LeadList
{
    private int $id;

    /**
     * @var array<mixed>
     */
    private array $filters;

    public function __construct(CompanySegment $companySegment)
    {
        $id = $companySegment->getId();
        \assert(null !== $id);

        $this->id      = $id;
        $this->filters = $companySegment->getFilters();

        parent::__construct();
    }

    public function getId(): int
    {
        $this->checkParentInterface();

        return $this->id;
    }

    /**
     * @return array<mixed>
     */
    public function getFilters(): array
    {
        $this->checkParentInterface();

        return $this->filters;
    }

    /**
     * Because Mautic does not have the interface for a "segment" entity,
     * need to kind of implement the class that implements only methods used by the filtering services.
     */
    private function checkParentInterface(): void
    {
        $parentClass   = new \ReflectionClass(parent::class);
        $parentMethods = [];
        foreach ($parentClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $parentMethods[] = $method->name;
        }

        $thisClass = new \ReflectionClass(self::class);
        $methods   = [];
        foreach ($thisClass->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->class === $thisClass->getName()) {
                $methods[] = $method->name;
            }
        }

        $diff = array_diff($methods, $parentMethods);
        if ([] === $diff) {
            return;
        }

        throw new \RuntimeException('The class '.self::class.' does not override all methods of the '.parent::class.'. Methods left: '.implode(', ', $diff));
    }

    public function hasFilterTypeOf(string $type): bool
    {
        throw new \Exception('Not implementing. This is a proxy.');
    }

    /**
     * @param array<mixed> $filters
     */
    public function setFilters(array $filters): self
    {
        throw new \Exception('Not implementing. This is a proxy.');
    }

    public static function loadMetadata(ORM\ClassMetadata $metadata): void
    {
        throw new \Exception('Not implementing. This is a proxy.');
    }

    public static function loadValidatorMetadata(ClassMetadata $metadata): void
    {
        throw new \Exception('Not implementing. This is a proxy.');
    }

    public static function loadApiMetadata(ApiMetadataDriver $metadata): void
    {
        throw new \Exception('Not implementing. This is a proxy.');
    }

    public function getName(): ?string
    {
        throw new \Exception('Not implementing. This is a proxy.');
    }

    /**
     * @param string|null $description
     */
    public function setDescription($description): self
    {
        throw new \Exception('Not implementing. This is a proxy.');
    }

    public function getDescription(): ?string
    {
        throw new \Exception('Not implementing. This is a proxy.');
    }

    public function setCategory(?Category $category = null): self
    {
        throw new \Exception('Not implementing. This is a proxy.');
    }

    public function getCategory(): ?Category
    {
        throw new \Exception('Not implementing. This is a proxy.');
    }

    public function getPublicName(): ?string
    {
        throw new \Exception('Not implementing. This is a proxy.');
    }

    /**
     * @param string|null $publicName
     */
    public function setPublicName($publicName): self
    {
        throw new \Exception('Not implementing. This is a proxy.');
    }

    /**
     * @param bool $isGlobal
     */
    public function setIsGlobal($isGlobal): self
    {
        throw new \Exception('Not implementing. This is a proxy.');
    }

    public function getIsGlobal(): bool
    {
        throw new \Exception('Not implementing. This is a proxy.');
    }

    public function isGlobal(): bool
    {
        throw new \Exception('Not implementing. This is a proxy.');
    }

    /**
     * @param string|null $alias
     */
    public function setAlias($alias): self
    {
        throw new \Exception('Not implementing. This is a proxy.');
    }

    public function getAlias(): ?string
    {
        throw new \Exception('Not implementing. This is a proxy.');
    }

    /**
     * @return Collection<int, Lead>
     */
    public function getLeads(): Collection
    {
        throw new \Exception('Not implementing. This is a proxy.');
    }

    public function __clone()
    {
        throw new \Exception('Not implementing. This is a proxy.');
    }

    public function getIsPreferenceCenter(): bool
    {
        throw new \Exception('Not implementing. This is a proxy.');
    }

    /**
     * @param bool $isPreferenceCenter
     */
    public function setIsPreferenceCenter($isPreferenceCenter): void
    {
        throw new \Exception('Not implementing. This is a proxy.');
    }

    public function getLastBuiltDate(): ?\DateTimeInterface
    {
        throw new \Exception('Not implementing. This is a proxy.');
    }

    public function setLastBuiltDate(?\DateTime $lastBuiltDate): void
    {
        throw new \Exception('Not implementing. This is a proxy.');
    }

    public function setLastBuiltDateToCurrentDatetime(): void
    {
        throw new \Exception('Not implementing. This is a proxy.');
    }

    public function initializeLastBuiltDate(): void
    {
        throw new \Exception('Not implementing. This is a proxy.');
    }

    public function getLastBuiltTime(): ?float
    {
        throw new \Exception('Not implementing. This is a proxy.');
    }

    public function setLastBuiltTime(?float $lastBuiltTime): void
    {
        throw new \Exception('Not implementing. This is a proxy.');
    }
}
