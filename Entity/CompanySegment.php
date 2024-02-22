<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping\ClassMetadata as ORMClassMetadata;
use Mautic\ApiBundle\Serializer\Driver\ApiMetadataDriver;
use Mautic\CategoryBundle\Entity\Category;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;
use Mautic\CoreBundle\Entity\FormEntity;
use Mautic\CoreBundle\Helper\DateTimeHelper;
use Mautic\LeadBundle\Entity\Company;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Validator\Constraints\UniqueAlias;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Mapping\ClassMetadata;

class CompanySegment extends FormEntity
{
    public const TABLE_NAME = 'company_segments';

    private ?int $id = null;

    private ?string $name = null;

    private ?string $publicName = null;

    private ?Category $category = null;

    private ?string $description = null;

    private ?string $alias = null;

    /**
     * @var array<mixed>
     */
    private array $filters = [];

    private bool $isGlobal = true;

    private bool $isPreferenceCenter = false;

    /**
     * @var Collection<Company>
     */
    private Collection $companies;

    private ?\DateTimeInterface $lastBuiltDate = null;

    private ?float $lastBuiltTime = null;

    public function __construct()
    {
        $this->companies = new ArrayCollection();
    }

    public static function loadMetadata(ORMClassMetadata $metadata): void
    {
        $builder = new ClassMetadataBuilder($metadata);

        $builder->setTable(self::TABLE_NAME)
            ->setCustomRepositoryClass(CompanySegmentRepository::class)
            ->addLifecycleEvent('initializeLastBuiltDate', 'prePersist')
            ->addIndex(['alias'], 'lead_list_alias');

        $builder->addIdColumns();

        $builder->addField('alias', 'string');

        $builder->createField('publicName', 'string')
            ->columnName('public_name')
            ->build();

        $builder->addCategory();

        $builder->addField('filters', 'json');

        $builder->createField('isGlobal', 'boolean')
            ->columnName('is_global')
            ->build();

        $builder->createField('isPreferenceCenter', 'boolean')
            ->columnName('is_preference_center')
            ->build();

        $builder->createManyToMany('companies', Company::class)
            ->setJoinTable('company_segment_xref')
            ->setIndexBy('id')
            ->addInverseJoinColumn('segment_id', 'id', false, false, 'CASCADE')
            ->addJoinColumn('company_id', 'id', true, false, 'CASCADE')
            ->build();

        $builder->createField('lastBuiltDate', 'datetime')
            ->columnName('last_built_date')
            ->nullable()
            ->build();

        $builder->createField('lastBuiltTime', 'float')
            ->columnName('last_built_time')
            ->nullable()
            ->build();
    }

    public static function loadValidatorMetadata(ClassMetadata $metadata): void
    {
        $metadata->addPropertyConstraint('name', new NotBlank(
            ['message' => 'mautic.core.name.required']
        ));

        $metadata->addConstraint(new UniqueAlias([
            'field'   => 'alias',
            'message' => 'mautic.lead.list.alias.unique',
        ]));
    }

    /**
     * Prepares the metadata for API usage.
     */
    public static function loadApiMetadata(ApiMetadataDriver $metadata): void
    {
        $metadata->setGroupPrefix('companySegment')
            ->addListProperties(
                [
                    'id',
                    'name',
                    'publicName',
                    'alias',
                    'description',
                    'category',
                ]
            )
            ->addProperties(
                [
                    'filters',
                    'isGlobal',
                    'isPreferenceCenter',
                ]
            )
            ->build();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setName(?string $name): self
    {
        $this->isChanged('name', $name);
        $this->name = $name;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setDescription(?string $description): self
    {
        $this->isChanged('description', $description);
        $this->description = $description;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setCategory(?Category $category): self
    {
        $this->isChanged('category', $category);
        $this->category = $category;

        return $this;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function getPublicName(): ?string
    {
        return $this->publicName;
    }

    public function setPublicName(?string $publicName): self
    {
        $this->isChanged('publicName', $publicName);
        $this->publicName = $publicName;

        return $this;
    }

    /**
     * @param array<mixed> $filters
     */
    public function setFilters(array $filters): self
    {
        $this->isChanged('filters', $filters);
        $this->filters = $filters;

        return $this;
    }

    /**
     * @return array<mixed>
     */
    public function getFilters(): array
    {
        // Copy from PR #12214
        $filters = $this->filters;
        foreach ($filters as &$filter) {
            $filter['glue'] = 'and';
            break;
        }

        return $filters;
    }

    public function setIsGlobal(bool $isGlobal): self
    {
        $this->isChanged('isGlobal', $isGlobal);
        $this->isGlobal = $isGlobal;

        return $this;
    }

    public function isGlobal(): bool
    {
        return $this->isGlobal;
    }

    public function setAlias(?string $alias): self
    {
        $this->isChanged('alias', $alias);
        $this->alias = $alias;

        return $this;
    }

    public function getAlias(): ?string
    {
        return $this->alias;
    }

    /**
     * @return Collection<int, Company>
     */
    public function getCompanies(): Collection
    {
        return $this->companies;
    }

    public function getIsPreferenceCenter(): bool
    {
        return $this->isPreferenceCenter;
    }

    public function setIsPreferenceCenter(bool $isPreferenceCenter): void
    {
        $this->isChanged('isPreferenceCenter', $isPreferenceCenter);
        $this->isPreferenceCenter = $isPreferenceCenter;
    }

    public function getLastBuiltDate(): ?\DateTimeInterface
    {
        return $this->lastBuiltDate;
    }

    public function setLastBuiltDate(?\DateTimeInterface $lastBuiltDate): void
    {
        $this->lastBuiltDate = $lastBuiltDate;
    }

    public function setLastBuiltDateToCurrentDatetime(): void
    {
        $now = (new DateTimeHelper())->getUtcDateTime();
        $this->setLastBuiltDate($now);
    }

    public function initializeLastBuiltDate(): void
    {
        if ($this->getLastBuiltDate() instanceof \DateTimeInterface) {
            return;
        }

        $this->setLastBuiltDateToCurrentDatetime();
    }

    public function getLastBuiltTime(): ?float
    {
        return $this->lastBuiltTime;
    }

    public function setLastBuiltTime(?float $lastBuiltTime): void
    {
        $this->lastBuiltTime = $lastBuiltTime;
    }

    /**
     * Clone entity with empty companies list.
     */
    public function __clone()
    {
        parent::__clone();

        $this->id        = null;
        $this->companies = new ArrayCollection();
        $this->setIsPublished(false);
        $this->setAlias('');
        $this->lastBuiltDate = null;
    }
}
