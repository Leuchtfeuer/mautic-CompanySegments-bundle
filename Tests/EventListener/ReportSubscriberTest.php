<?php

namespace EventListener;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Mautic\ReportBundle\Event\ReportBuilderEvent;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\EventListener\ReportSubscriber;
use Mautic\LeadBundle\Model\CompanyReportData;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegmentRepository;
use Doctrine\DBAL\Connection;

class ReportSubscriberTest extends TestCase
{

    private ReportSubscriber $reportSubscriber;
    private MockObject $reportBuilderEventMock;
    private MockObject $companyReportDataMock;
    private MockObject $companySegmentRepositoryMock;
    private MockObject $dbMock;

    protected function setUp(): void{
        $this->reportBuilderEventMock             = $this->createMock(ReportBuilderEvent::class);
        $this->companyReportDataMock              = $this->createMock(CompanyReportData::class);
        $this->companySegmentRepositoryMock       = $this->createMock(CompanySegmentRepository::class);
        $this->dbMock                             = $this->createMock(Connection::class);
        $this->reportSubscriber                 = new ReportSubscriber(
            $this->companyReportDataMock,
            $this->companySegmentRepositoryMock,
            $this->dbMock
        );
    }


    public function testCheckIfEventHasCompanySegmentContext()
    {
        $columns = [
            'comp.id' => [
                'alias' => 'comp_id',
                'label' => 'mautic.lead.report.company.company_id',
                'type' => 'int',
                'link' => 'mautic_company_action'
            ],
            'companies_lead.is_primary' => [
                'label' => 'mautic.lead.report.company.is_primary',
                'type' => 'bool'
            ],
            'companies_lead.date_added' => [
                'label' => 'mautic.lead.report.company.date_added',
                'type' => 'datetime'
            ],
            'comp.companyaddress1' => [
                'label' => 'Company Address 1',
                'type' => 'string'
            ],
            'comp.companyaddress2' => [
                'label' => 'Company Address 2',
                'type' => 'string'
            ],
            'comp.companyemail' => [
                'label' => 'Company Company Email',
                'type' => 'email'
            ],
            'comp.companyphone' => [
                'label' => 'Company Phone',
                'type' => 'string'
            ],
            'comp.companycity' => [
                'label' => 'Company City',
                'type' => 'string'
            ],
            'comp.companystate' => [
                'label' => 'Company State',
                'type' => 'string'
            ],
            'comp.companyzipcode' => [
                'label' => 'Company Zip Code',
                'type' => 'string'
            ],
            'comp.companycountry' => [
                'label' => 'Company Country',
                'type' => 'string'
            ],
            'comp.companyname' => [
                'label' => 'Company Company Name',
                'type' => 'string'
            ],
            'comp.companywebsite' => [
                'label' => 'Company Website',
                'type' => 'url'
            ],
            'comp.companynumber_of_employees' => [
                'label' => 'Company Number of Employees',
                'type' => 'float'
            ],
            'comp.companyfax' => [
                'label' => 'Company Fax',
                'type' => 'string'
            ],
            'comp.companyannual_revenue' => [
                'label' => 'Company Annual Revenue',
                'type' => 'float'
            ],
            'comp.companyindustry' => [
                'label' => 'Company Industry',
                'type' => 'string'
            ],
            'comp.companydescription' => [
                'label' => 'Company Description',
                'type' => 'string'
            ]
        ];

        $this->reportBuilderEventMock->expects($this->once())
            ->method('checkContext')
            ->willReturn(true);

        $this->companyReportDataMock->expects($this->once())
            ->method('getCompanyData')
            ->willReturn($columns);

        //checken ob filteredColumns count 16 ist (weil auf 16 keys reduziert werden soll)



        $this->reportSubscriber->onReportBuilder($this->reportBuilderEventMock);


    }

    //getFilterSegmentsTest
    //fixtures laufen lassen
    //Funktion ausführen (muss eventuell noch sagen, was Funktion returnen soll, wäre aber besser über fixtures)
    //return value spezifizieren

}