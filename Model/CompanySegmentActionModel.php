<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model;

use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Model\CompanyModel;

class CompanySegmentActionModel
{
    public function __construct(
        private CompanyModel $companyModel,
        private CompanySegmentModel $companySegmentModel,
        private CorePermissions $security
    ) {
    }

    /**
     * @param array<int> $companyIds
     * @param array<int> $segmentIds
     */
    public function addCompanies(array $companyIds, array $segmentIds): void
    {
        $companies = $this->getCompaniesByIds($companyIds);

        foreach ($companies as $company) {
            if (!$this->canEditCompany($company)) {
                continue;
            }

            $this->companySegmentModel->addCompany($company, $segmentIds);
        }
    }

    /**
     * @param array<int> $companyIds
     * @param array<int> $segmentIds
     */
    public function removeCompanies(array $companyIds, array $segmentIds): void
    {
        $contacts = $this->getCompaniesByIds($companyIds);

        foreach ($contacts as $contact) {
            if (!$this->canEditCompany($contact)) {
                continue;
            }

            $this->companySegmentModel->removeCompany($contact, $segmentIds);
        }
    }

    /**
     * @param array<int> $ids
     *
     * @return array<Company>
     */
    private function getCompaniesByIds(array $ids): array
    {
        $result = $this->companyModel->getEntities([
            'filter' => [
                'force' => [
                    [
                        'column' => 'comp.id',
                        'expr'   => 'in',
                        'value'  => $ids,
                    ],
                ],
            ],
        ]);

        if (!is_array($result)) {
            throw new \RuntimeException('The mautic changed it\'s behaviour.');
        }

        return $result;
    }

    public function canEditCompany(Company $company): bool
    {
        return $this->security->hasEntityAccess('lead:leads:editown', 'lead:leads:editother', $company->getPermissionUser());
    }
}
