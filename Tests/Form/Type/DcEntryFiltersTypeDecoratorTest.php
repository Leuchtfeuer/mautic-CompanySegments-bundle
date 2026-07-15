<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Tests\Form\Type;

use Mautic\LeadBundle\Model\ListModel;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Form\Type\DcEntryFiltersTypeDecorator;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanySegmentModel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class DcEntryFiltersTypeDecoratorTest extends TestCase
{
    private ListModel $listModel;
    private CompanySegmentModel $companySegmentModel;
    private DcEntryFiltersTypeDecorator $decorator;

    protected function setUp(): void
    {
        $this->listModel           = $this->createMock(ListModel::class);
        $this->companySegmentModel = $this->createMock(CompanySegmentModel::class);

        $this->listModel->method('getChoiceFields')->willReturn(
            ['lead' => ['leadlist' => ['properties' => ['list' => []]]]]
        );

        $this->decorator = new DcEntryFiltersTypeDecorator(
            $this->createMock(TranslatorInterface::class),
            $this->listModel,
            $this->companySegmentModel
        );
    }

    public function testPreProcessDoesNothingForNonCompanySegmentsType(): void
    {
        $data = ['type' => 'leadlist', 'operator' => 'in', 'filter' => [1]];

        $event = new FormEvent($this->createMock(FormInterface::class), $data);
        $this->invokePreProcess($event);

        $this->assertSame($data, $event->getData());
    }

    public function testPreProcessConvertsCompanySegmentsTypeToText(): void
    {
        $data = ['type' => 'company_segments', 'operator' => 'in', 'filter' => [1, 2]];

        $event = new FormEvent($this->createMock(FormInterface::class), $data);
        $this->invokePreProcess($event);

        $result = $event->getData();
        $this->assertSame('text', $result['type']);
        $this->assertSame('company_segments', $result['__original_type']);
        $this->assertSame('in', $result['__original_operator']);
        $this->assertSame([1, 2], $result['filter']);
    }

    public function testPreProcessSetsEmptyArrayWhenFilterMissing(): void
    {
        $data = ['type' => 'company_segments', 'operator' => 'empty'];

        $event = new FormEvent($this->createMock(FormInterface::class), $data);
        $this->invokePreProcess($event);

        $this->assertSame([], $event->getData()['filter']);
    }

    public function testPreProcessWrapsScalarFilterInArray(): void
    {
        $data = ['type' => 'company_segments', 'operator' => 'in', 'filter' => '5'];

        $event = new FormEvent($this->createMock(FormInterface::class), $data);
        $this->invokePreProcess($event);

        $this->assertSame(['5'], $event->getData()['filter']);
    }

    public function testPostProcessDoesNothingWhenOriginalTypeNotSet(): void
    {
        $data = ['type' => 'leadlist', 'filter' => [1]];

        $form = $this->createMock(FormInterface::class);
        $form->expects($this->never())->method('remove');

        $event = new FormEvent($form, $data);
        $this->invokePostProcess($event, []);

        $this->assertSame($data, $event->getData());
    }

    public function testPostProcessWithOperatorInAddsMultipleChoiceField(): void
    {
        $this->companySegmentModel->method('getCompanySegments')->willReturn([
            ['id' => 1, 'name' => 'Segment A'],
            ['id' => 2, 'name' => 'Segment B'],
        ]);

        $data = [
            '__original_type'     => 'company_segments',
            '__original_operator' => 'in',
            'operator'            => 'in',
            'type'                => 'text',
            'filter'              => [1],
        ];

        $form = $this->createMock(FormInterface::class);
        $form->method('has')->with('filter')->willReturn(true);
        $form->expects($this->once())->method('remove')->with('filter');
        $form->expects($this->once())->method('add')->with(
            'filter',
            ChoiceType::class,
            $this->callback(fn (array $opts): bool => true === $opts['multiple'])
        );

        $event = new FormEvent($form, $data);
        $this->invokePostProcess($event, ['companySegments' => ['Segment A' => 1, 'Segment B' => 2]]);

        $result = $event->getData();
        $this->assertSame('company_segments', $result['type']);
        $this->assertArrayNotHasKey('__original_type', $result);
        $this->assertArrayNotHasKey('__original_operator', $result);
    }

    public function testPostProcessWithOperatorNotInAddsMultipleChoiceField(): void
    {
        $data = [
            '__original_type'     => 'company_segments',
            '__original_operator' => '!in',
            'operator'            => '!in',
            'type'                => 'text',
            'filter'              => [1],
        ];

        $form = $this->createMock(FormInterface::class);
        $form->method('has')->with('filter')->willReturn(false);
        $form->expects($this->once())->method('add')->with(
            'filter',
            ChoiceType::class,
            $this->callback(fn (array $opts): bool => true === $opts['multiple'])
        );

        $event = new FormEvent($form, $data);
        $this->invokePostProcess($event, ['companySegments' => []]);
    }

    public function testPostProcessWithOperatorEmptyAddsSingleChoiceField(): void
    {
        $data = [
            '__original_type'     => 'company_segments',
            '__original_operator' => 'empty',
            'operator'            => 'empty',
            'type'                => 'text',
            'filter'              => [],
        ];

        $form = $this->createMock(FormInterface::class);
        $form->method('has')->with('filter')->willReturn(false);
        $form->expects($this->once())->method('add')->with(
            'filter',
            ChoiceType::class,
            $this->callback(fn (array $opts): bool => false === $opts['multiple'])
        );

        $event = new FormEvent($form, $data);
        $this->invokePostProcess($event, ['companySegments' => []]);
    }

    public function testPostProcessWithOperatorNotEmptyAddsSingleChoiceField(): void
    {
        $data = [
            '__original_type'     => 'company_segments',
            '__original_operator' => '!empty',
            'operator'            => '!empty',
            'type'                => 'text',
            'filter'              => [],
        ];

        $form = $this->createMock(FormInterface::class);
        $form->method('has')->with('filter')->willReturn(false);
        $form->expects($this->once())->method('add')->with(
            'filter',
            ChoiceType::class,
            $this->callback(fn (array $opts): bool => false === $opts['multiple'])
        );

        $event = new FormEvent($form, $data);
        $this->invokePostProcess($event, ['companySegments' => []]);
    }

    private function invokePreProcess(FormEvent $event): void
    {
        $method = new \ReflectionMethod($this->decorator, 'preProcessCompanySegments');
        $method->setAccessible(true);
        $method->invoke($this->decorator, $event);
    }

    /**
     * @param array<string, mixed> $options
     */
    private function invokePostProcess(FormEvent $event, array $options): void
    {
        $method = new \ReflectionMethod($this->decorator, 'postProcessCompanySegments');
        $method->setAccessible(true);
        $method->invoke($this->decorator, $event, $options);
    }
}
