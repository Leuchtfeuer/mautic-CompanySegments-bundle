<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\EventListener;

use Doctrine\Common\Collections\ArrayCollection;
use Mautic\AssetBundle\Model\AssetModel;
use Mautic\CampaignBundle\Model\CampaignModel;
use Mautic\CategoryBundle\Model\CategoryModel;
use Mautic\EmailBundle\Model\EmailModel;
use Mautic\LeadBundle\Entity\LeadField;
use Mautic\LeadBundle\Entity\LeadFieldRepository;
use Mautic\LeadBundle\Event\FormAdjustmentEvent;
use Mautic\LeadBundle\Event\ListFieldChoicesEvent;
use Mautic\LeadBundle\Exception\ChoicesNotFoundException;
use Mautic\LeadBundle\LeadEvents;
use Mautic\LeadBundle\Model\LeadModel;
use Mautic\LeadBundle\Model\ListModel;
use Mautic\LeadBundle\Provider\FieldChoicesProviderInterface;
use Mautic\LeadBundle\Provider\TypeOperatorProviderInterface;
use Mautic\LeadBundle\Segment\OperatorOptions;
use Mautic\StageBundle\Model\StageModel;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Event\CompanySegmentEvents;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Event\CompanySegmentFiltersChoicesEvent;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\EventListener\TypeOperatorSubscriber;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanySegmentModel;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Contracts\Translation\TranslatorInterface;

class TypeOperatorSubscriberTest extends TestCase
{
    /**
     * @var MockObject&LeadFieldRepository
     */
    private MockObject $leadFieldRepository;

    /**
     * @var MockObject&CompanySegmentModel
     */
    private MockObject $companySegmentModel;

    /**
     * @var MockObject&TypeOperatorProviderInterface
     */
    private MockObject $typeOperatorProvider;

    /**
     * @var MockObject&FieldChoicesProviderInterface
     */
    private MockObject $fieldChoicesProvider;

    /**
     * @var MockObject&TranslatorInterface
     */
    private MockObject $translator;

    /**
     * @var MockObject&FormInterface<FormInterface>
     */
    private MockObject $form;

    private TypeOperatorSubscriber $subscriber;

    private \Mautic\LeadBundle\EventListener\TypeOperatorSubscriber $typeOperatorSubscriber;

    protected function setUp(): void
    {
        parent::setUp();

        $this->leadFieldRepository    = $this->createMock(LeadFieldRepository::class);
        $this->companySegmentModel    = $this->createMock(CompanySegmentModel::class);
        $this->typeOperatorProvider   = $this->createMock(TypeOperatorProviderInterface::class);
        $this->fieldChoicesProvider   = $this->createMock(FieldChoicesProviderInterface::class);
        $this->translator             = $this->createMock(TranslatorInterface::class);
        $this->form                   = $this->createMock(FormInterface::class);
        $this->typeOperatorSubscriber = new \Mautic\LeadBundle\EventListener\TypeOperatorSubscriber(
            $this->createMock(LeadModel::class),
            $this->createMock(ListModel::class),
            $this->createMock(CampaignModel::class),
            $this->createMock(EmailModel::class),
            $this->createMock(StageModel::class),
            $this->createMock(CategoryModel::class),
            $this->createMock(AssetModel::class),
            $this->translator
        );

        $this->subscriber = new TypeOperatorSubscriber(
            $this->typeOperatorSubscriber,
            $this->leadFieldRepository,
            $this->companySegmentModel,
            $this->typeOperatorProvider,
            $this->fieldChoicesProvider,
            $this->translator
        );
    }

    public function testOnTypeListCollect(): void
    {
        $event = new ListFieldChoicesEvent();

        $this->companySegmentModel->expects(self::once())
            ->method('getCompanySegments')
            ->willReturn([
                ['id' => 22, 'name' => 'Company A'],
                ['id' => 37, 'name' => 'Company B'],
            ]);

        $this->subscriber->onTypeListCollect($event);

        $choicesForAliases = $event->getChoicesForAllListFieldAliases();
        $choicesForTypes   = $event->getChoicesForAllListFieldTypes();

        self::assertSame(['Company A' => 22, 'Company B' => 37], $choicesForAliases['company_segments']);
        self::assertCount(0, $choicesForTypes);
    }

    public function testOnSegmentFilterFormHandleSelectIfNotCompanySegment(): void
    {
        $alias    = 'select_a';
        $object   = 'lead';
        $operator = OperatorOptions::IN;
        $details  = ['properties' => ['type' => 'unicorn']];
        $event    = new FormAdjustmentEvent($this->form, $alias, $object, $operator, $details);

        $this->form->expects(self::never())
            ->method('add');

        $this->subscriber->onSegmentFilterFormHandleSelect($event);
    }

    public function testOnSegmentFilterFormHandleSelectIfSelectWithRegexpOperator(): void
    {
        $alias    = 'select_a';
        $object   = 'lead';
        $operator = OperatorOptions::REGEXP;
        $details  = [
            'properties' => [
                'type' => 'select',
                'list' => ['Choice A' => 'choice_a'],
            ],
        ];
        $event = new FormAdjustmentEvent($this->form, $alias, $object, $operator, $details);

        $this->form->expects(self::never())
            ->method('add');

        $this->subscriber->onSegmentFilterFormHandleSelect($event);
    }

    public function testOnSegmentFilterFormHandleSelectIfSelect(): void
    {
        $alias    = 'select_a';
        $object   = 'lead';
        $operator = OperatorOptions::IN;
        $details  = [
            'properties' => [
                'type' => 'company_segments',
                'list' => [
                    'Choice A' => 'choice_a',
                ],
            ],
        ];
        $event = new FormAdjustmentEvent($this->form, $alias, $object, $operator, $details);

        $this->form->expects(self::once())
            ->method('add')
            ->with(
                'filter',
                ChoiceType::class,
                [
                    'label'                     => false,
                    'attr'                      => ['class' => 'form-control'],
                    'data'                      => [],
                    'choices'                   => ['Choice A' => 'choice_a'],
                    'multiple'                  => true,
                    'choice_translation_domain' => false,
                    'disabled'                  => false,
                    'constraints'               => [new NotBlank(['message' => 'mautic.core.value.required'])],
                ]
            );

        $this->subscriber->onSegmentFilterFormHandleSelect($event);
    }

    public function testOnGenerateSegmentFiltersAddCustomFieldsForBooleanTypes(): void
    {
        $field = new LeadField();
        $event = new CompanySegmentFiltersChoicesEvent([], [], $this->translator);

        $field->setType('boolean');
        $field->setLabel('Test Bool');
        $field->setAlias('test_bool');
        $field->setObject('company');
        $field->setProperties([
            'no'  => 'No',
            'yes' => 'Yes',
        ]);

        $this->leadFieldRepository->expects(self::once())
            ->method('getListablePublishedFields')
            ->willReturn(new ArrayCollection([$field]));

        $this->typeOperatorProvider->expects(self::once())
            ->method('getOperatorsForFieldType')
            ->with('boolean')
            ->willReturn(
                [
                    'equals'    => '=',
                    'not equal' => '!=',
                ]
            );

        $this->subscriber->onGenerateSegmentFiltersAddCustomFields($event);

        self::assertSame(
            [
                'company' => [
                    'test_bool' => [
                        'label'      => 'Test Bool',
                        'properties' => [
                            'no'   => 'No',
                            'yes'  => 'Yes',
                            'type' => 'boolean',
                            'list' => [
                                'No'  => 0,
                                'Yes' => 1,
                            ],
                        ],
                        'object'    => 'company',
                        'operators' => [
                            'equals'    => '=',
                            'not equal' => '!=',
                        ],
                    ],
                ],
            ],
            $event->getChoices()
        );
    }

    public function testOnGenerateSegmentFiltersAddCustomFieldsForSelectTypes(): void
    {
        $field = new LeadField();
        $event = new CompanySegmentFiltersChoicesEvent([], [], $this->translator);

        $field->setType('select');
        $field->setLabel('Test Select');
        $field->setAlias('test_select');
        $field->setObject('company');
        $field->setProperties([
            'list' => [
                'one' => 'One',
                'two' => 'Two',
            ],
        ]);

        $this->leadFieldRepository->expects(self::once())
            ->method('getListablePublishedFields')
            ->willReturn(new ArrayCollection([$field]));

        $this->typeOperatorProvider->expects(self::once())
            ->method('getOperatorsForFieldType')
            ->with('select')
            ->willReturn(
                [
                    'equals'    => '=',
                    'not equal' => '!=',
                ]
            );

        $this->subscriber->onGenerateSegmentFiltersAddCustomFields($event);

        self::assertSame(
            [
                'company' => [
                    'test_select' => [
                        'label'      => 'Test Select',
                        'properties' => [
                            'list' => [
                                'One' => 'one',
                                'Two' => 'two',
                            ],
                            'type' => 'select',
                        ],
                        'object'    => 'company',
                        'operators' => [
                            'equals'    => '=',
                            'not equal' => '!=',
                        ],
                    ],
                ],
            ],
            $event->getChoices()
        );
    }

    public function testOnGenerateSegmentFiltersAddCustomFieldsForCountryTypes(): void
    {
        $field = new LeadField();
        $event = new CompanySegmentFiltersChoicesEvent([], [], $this->translator);

        $field->setType('country');
        $field->setObject('company');
        $field->setLabel('Test Country');
        $field->setAlias('test_country');

        $this->leadFieldRepository->expects(self::once())
            ->method('getListablePublishedFields')
            ->willReturn(new ArrayCollection([$field]));

        $this->typeOperatorProvider->expects(self::once())
            ->method('getOperatorsForFieldType')
            ->with('country')
            ->willReturn(
                [
                    'equals'    => '=',
                    'not equal' => '!=',
                ]
            );

        $this->fieldChoicesProvider->expects(self::once())
            ->method('getChoicesForField')
            ->with('country')
            ->willReturn(
                [
                    'Czech Republic'  => 'Czech Republic',
                    'Slovak Republic' => 'Slovak Republic',
                ]
            );

        $this->subscriber->onGenerateSegmentFiltersAddCustomFields($event);

        self::assertSame(
            [
                'company' => [
                    'test_country' => [
                        'label'      => 'Test Country',
                        'properties' => [
                            'type' => 'country',
                            'list' => [
                                'Czech Republic'  => 'Czech Republic',
                                'Slovak Republic' => 'Slovak Republic',
                            ],
                        ],
                        'object'    => 'company',
                        'operators' => [
                            'equals'    => '=',
                            'not equal' => '!=',
                        ],
                    ],
                ],
            ],
            $event->getChoices()
        );
    }

    public function testOnGenerateSegmentFiltersAddCustomFieldsForTextTypes(): void
    {
        $field = new LeadField();
        $event = new CompanySegmentFiltersChoicesEvent([], [], $this->translator);

        $field->setType('text');
        $field->setLabel('Test Text');
        $field->setAlias('test_text');
        $field->setObject('company');

        $this->leadFieldRepository->expects(self::once())
            ->method('getListablePublishedFields')
            ->willReturn(new ArrayCollection([$field]));

        $this->typeOperatorProvider->expects(self::once())
            ->method('getOperatorsForFieldType')
            ->with('text')
            ->willReturn(
                [
                    'equals'    => '=',
                    'not equal' => '!=',
                ]
            );

        $this->fieldChoicesProvider->expects(self::once())
            ->method('getChoicesForField')
            ->with('text')
            ->willThrowException(new ChoicesNotFoundException());

        $this->subscriber->onGenerateSegmentFiltersAddCustomFields($event);

        self::assertSame(
            [
                'company' => [
                    'test_text' => [
                        'label'      => 'Test Text',
                        'properties' => [
                            'type' => 'text',
                        ],
                        'object'    => 'company',
                        'operators' => [
                            'equals'    => '=',
                            'not equal' => '!=',
                        ],
                    ],
                ],
            ],
            $event->getChoices()
        );
    }

    public function testOnGenerateSegmentFiltersAddStaticFields(): void
    {
        // Only displays on segment actions
        $request = new Request();
        $request->attributes->set('_route', 'mautic_company_segment_action');

        $choices = [
            'group' => [
                'alias' => [
                    'properties' => [
                        'type' => 'text',
                    ],
                ],
            ],
        ];
        $event = new CompanySegmentFiltersChoicesEvent($choices, [], $this->translator, $request);

        $this->typeOperatorProvider
            ->method('getOperatorsForFieldType')
            ->willReturn(
                [
                    'equals'    => '=',
                    'not equal' => '!=',
                ]
            );

        $this->typeOperatorProvider->expects(self::once())
            ->method('getOperatorsIncluding')
            ->with([
                OperatorOptions::EQUAL_TO,
                OperatorOptions::NOT_EQUAL_TO,
                OperatorOptions::EMPTY,
                OperatorOptions::NOT_EMPTY,
                OperatorOptions::LIKE,
                OperatorOptions::NOT_LIKE,
                OperatorOptions::REGEXP,
                OperatorOptions::NOT_REGEXP,
                OperatorOptions::IN,
                OperatorOptions::NOT_IN,
                OperatorOptions::STARTS_WITH,
                OperatorOptions::ENDS_WITH,
                OperatorOptions::CONTAINS,
            ])
            ->willReturn(
                [
                    'equals'    => '=',
                    'not equal' => '!=',
                ]
            );

        $this->fieldChoicesProvider
            ->method('getChoicesForField')
            ->willReturn(
                [
                    'Choice A' => 'choice_a',
                    'Choice B' => 'choice_b',
                ]
            );

        $this->translator
            ->method('trans')
            ->willReturnArgument(0);

        $this->subscriber->onGenerateSegmentFiltersAddStaticFields($event);

        $choices = $event->getChoices();

        self::assertCount(3, $choices['company_segments']);

        // Test for some random choices:
        self::assertSame(
            [
                'label'      => 'mautic.core.date.added',
                'properties' => [
                    'type' => 'date',
                ],
                'operators' => [
                    'equals'    => '=',
                    'not equal' => '!=',
                ],
                'object' => 'company',
            ],
            $choices['company_segments']['date_added']
        );

        self::assertSame(
            [
                'label'      => 'mautic.lead.list.filter.date_modified',
                'properties' => [
                    'type' => 'datetime',
                ],
                'operators' => [
                    'equals'    => '=',
                    'not equal' => '!=',
                ],
                'object' => 'company',
            ],
            $choices['company_segments']['date_modified']
        );

        self::assertSame(
            [
                'label'      => 'mautic.lead.list.filter.lists',
                'properties' => [
                    'type' => 'company_segments',
                    'list' => [
                        'Choice A' => 'choice_a',
                        'Choice B' => 'choice_b',
                    ],
                ],
                'operators' => [
                    'equals'    => '=',
                    'not equal' => '!=',
                ],
                'object' => 'company',
            ],
            $choices['company_segments'][CompanySegmentModel::PROPERTIES_FIELD]
        );
    }

    public function testSubscribedEvents(): void
    {
        self::assertSame([
            LeadEvents::COLLECT_FILTER_CHOICES_FOR_LIST_FIELD_TYPE => ['onTypeListCollect', 0],
            LeadEvents::ADJUST_FILTER_FORM_TYPE_FOR_FIELD          => [
                ['onSegmentFilterFormHandleSelect', 400],
            ],
            CompanySegmentEvents::SEGMENT_FILTERS_CHOICES_ON_GENERATE => [
                ['onGenerateSegmentFiltersAddStaticFields', 0],
                ['onGenerateSegmentFiltersAddCustomFields', 0],
            ],
        ], TypeOperatorSubscriber::getSubscribedEvents());
    }
}
