<?php

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Mautic\ApiBundle\Serializer\Driver\ApiMetadataDriver;
use Mautic\CoreBundle\Doctrine\Mapping\ClassMetadataBuilder;
use Mautic\LeadBundle\Entity\Company;

/**
 * Store here company events.
 */
class CompanyEventLog
{
    /**
     * @var string
     */
    public const INDEX_SEARCH = 'IDX_SEARCH';

    /**
     * @var string
     */
    protected $id;

    /**
     * @var Company|null
     */
    protected $company;

    /**
     * @var int|null
     */
    protected $userId;

    /**
     * @var string|null
     */
    protected $userName;

    /**
     * @var string|null
     */
    protected $bundle;

    /**
     * @var string|null
     */
    protected $object;

    /**
     * @var int|null
     */
    protected $objectId;

    /**
     * @var string|null
     */
    protected $action;

    /**
     * @var \DateTime|null
     */
    protected $dateAdded;

    /**
     * @var string|null
     */
    protected $properties;

    /**
     * @param ORM\ClassMetadata $metadata
     */
    public static function loadMetadata(ORM\ClassMetadata $metadata): void
    {
        $builder = new ClassMetadataBuilder($metadata);
        $builder->setTable('company_event_log')
            ->setCustomRepositoryClass(CompanyEventLogRepository::class)
            ->addIndex(['company_id'], 'company_id_index')
            ->addIndex(['object','object_id'], 'company_object_index')
            ->addIndex(['bundle', 'object', 'action', 'object_id'],'company_timeline_index')
            ->addIndex(['bundle', 'object', 'action', 'object_id','date_added'],self::INDEX_SEARCH)
            ->addIndex(['action'], 'company_timeline_action_index')
            ->addIndex(['date_added'], 'company_date_added_index')
            ->addBigIntIdField()
            ->addNullableField('userId', Types::INTEGER, 'user_id')
            ->addNullableField('userName', Types::STRING, 'user_name')
            ->addNullableField('bundle', Types::STRING)
            ->addNullableField('object', Types::STRING)
            ->addNullableField('action', Types::STRING)
            ->addNullableField('objectId', Types::INTEGER, 'object_id')
            ->addNamedField('dateAdded', Types::DATETIME_MUTABLE, 'date_added')
            ->addNullableField('properties', Types::JSON);

        $builder->createManyToOne('company', Company::class)
            ->addJoinColumn('company_id', 'id', true, false, 'CASCADE')
            ->inversedBy('eventLog')
            ->build();
    }

    public function __construct()
    {
        $this->setDateAdded(new \DateTime());
    }

    /**
     * Prepare the metadata for API usage.
     */
    public static function loadApiMetadata(ApiMetadataDriver $metadata): void
    {
        $metadata->setGroupPrefix('import')
            ->addListProperties([
                'id',
                'companyId',
                'userId',
                'userName',
                'bundle',
                'object',
                'objectId',
                'action',
                'dateAdded',
                'properties'
            ])
            ->build();
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return (int) $this->id;
    }

    /**
     * @return CompanyEventLog
     */
    public function setCompany(Company $company): self
    {
        $this->company = $company;
        return $this;
    }

    /**
     * @return Company|null
     */
    public function getCompany(): ?Company
    {
        return $this->company;
    }

    /**
     * @return int|null
     */
    public function getUserId(): ?int
    {
        return $this->userId;
    }

    /**
     * @return CompanyEventLog
     */
    public function setUserId(?int $userId): self
    {
        $this->userId = $userId;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getUserName(): ?string
    {
        return $this->userName;
    }

    /**
     * @return CompanyEventLog
     */
    public function setUserName(?string $userName): self
    {
        $this->userName = $userName;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getBundle(): ?string
    {
        return $this->bundle;
    }

    /**
     * @return CompanyEventLog
     */
    public function setBundle(?string $bundle): self
    {
        $this->bundle = $bundle;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getObject(): ?string
    {
        return $this->object;
    }

    /**
     * @return CompanyEventLog
     */
    public function setObject(?string $object): self
    {
        $this->object = $object;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getObjectId(): ?int
    {
        return $this->objectId;
    }

    /**
     * @return CompanyEventLog
     */
    public function setObjectId(?int $objectId): self
    {
        $this->objectId = $objectId;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getAction(): ?string
    {
        return $this->action;
    }

    /**
     * @return CompanyEventLog
     */
    public function setAction(?string $action): self
    {
        $this->action = $action;
        return $this;
    }

    /**
     * @return \DateTime|null
     */
    public function getDateAdded(): ?\DateTime
    {
        return $this->dateAdded;
    }

    /**
     * @return CompanyEventLog
     */
    public function setDateAdded(?\DateTime $dateAdded): self
    {
        $this->dateAdded = $dateAdded;
        return $this;
    }

    /**
     * @return string|null
     */
    public function getProperties(): ?string
    {
        return $this->properties;
    }

    /**
     * @return CompanyEventLog
     */
    public function setProperties(?string $properties): self
    {
        $this->properties = $properties;
        return $this;
    }

    /**
     * Set one property into the properties array.
     *
     * @param string $key
     * @param string $value
     *
     * @return CompanyEventLog
     */
    public function addProperty($key, $value): self
    {
        $this->properties[$key] = $value;

        return $this;
    }


}