<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Mautic\CoreBundle\Exception\SchemaException;
use Mautic\IntegrationsBundle\Migration\AbstractMigration;

class Version_6_0_1 extends AbstractMigration
{
    private string $companyEventLogTable           = 'company_event_log';
    private string $companiesPlaceholderLeadsTable = 'companies_placeholder_leads';
    private bool $needsCompanyEventLogTable        = false;
    private bool $needsPlaceholderLeadsTable       = false;

    protected function isApplicable(Schema $schema): bool
    {
        try {
            $companyEventLogTabelName         = $this->concatPrefix($this->companyEventLogTable);
            $companyPlaceholderLeadsTableName = $this->concatPrefix($this->companiesPlaceholderLeadsTable);
            $this->needsCompanyEventLogTable  = !$schema->hasTable($companyEventLogTabelName);
            $this->needsPlaceholderLeadsTable = !$schema->hasTable($companyPlaceholderLeadsTableName);

            return $this->needsCompanyEventLogTable || $this->needsPlaceholderLeadsTable;
        } catch (SchemaException) {
            return false;
        }
    }

    protected function up(): void
    {
        if (true === $this->needsCompanyEventLogTable) {
            $this->createCompanyEventLogTable();
        }

        if (true === $this->needsPlaceholderLeadsTable) {
            $this->createCompanyPlaceholderLeadsTable();
        }
    }

    private function createCompanyEventLogTable(): void
    {
        $companyEventLogTabelName = $this->concatPrefix($this->companyEventLogTable);
        $companiesTable           = $this->concatPrefix('companies');

        $this->addSql("
            CREATE TABLE `{$companyEventLogTabelName}` (
                `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                `company_id` int(11) DEFAULT NULL,
                `user_id` int(11) DEFAULT NULL,
                `user_name` varchar(191) DEFAULT NULL,
                `bundle` varchar(191) DEFAULT NULL,
                `object` varchar(191) DEFAULT NULL,
                `action` varchar(191) DEFAULT NULL,
                `object_id` int(11) DEFAULT NULL,
                `date_added` datetime NOT NULL,
                `properties` longtext DEFAULT NULL COMMENT '(DC2Type:json)',
                PRIMARY KEY (`id`),
                KEY `company_id_index` (`company_id`),
                KEY `company_object_index` (`object`,`object_id`),
                KEY `company_timeline_index` (`bundle`,`object`,`action`,`object_id`),
                KEY `IDX_SEARCH` (`bundle`,`object`,`action`,`object_id`,`date_added`),
                KEY `company_timeline_action_index` (`action`),
                KEY `company_date_added_index` (`date_added`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
        ");

        $this->addSql("
            ALTER TABLE `{$companyEventLogTabelName}`
            ADD CONSTRAINT `{$this->generatePropertyName($this->companyEventLogTable, 'fk', ['company_id'])}`
            FOREIGN KEY (`company_id`)
            REFERENCES `{$companiesTable}` (`id`)
            ON DELETE CASCADE;
        ");
    }

    private function createCompanyPlaceholderLeadsTable(): void
    {
        $companyPlaceholderLeadsTableName = $this->concatPrefix($this->companiesPlaceholderLeadsTable);
        $companiesTable                   = $this->concatPrefix('companies');
        $leadsTable                       = $this->concatPrefix('leads');

        $this->addSql("
            CREATE TABLE `{$companyPlaceholderLeadsTableName}` (
                `company_id` int(11) NOT NULL,
                `lead_id` bigint(20) unsigned NOT NULL,
                PRIMARY KEY (`company_id`),
                KEY `{$this->generatePropertyName($this->companiesPlaceholderLeadsTable, 'idx', ['lead_id'])}` (`lead_id`),
                CONSTRAINT `{$this->generatePropertyName($this->companiesPlaceholderLeadsTable, 'fk', ['lead_id'])}` FOREIGN KEY (`lead_id`) REFERENCES `{$leadsTable}` (`id`) ON DELETE CASCADE,
                CONSTRAINT `{$this->generatePropertyName($this->companiesPlaceholderLeadsTable, 'fk', ['company_id'])}` FOREIGN KEY (`company_id`) REFERENCES `{$companiesTable}` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT=DYNAMIC;
        ");
    }
}
