<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model;

use Doctrine\ORM\EntityManager;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Translation\Translator;
use Mautic\EmailBundle\Helper\EmailValidator;
use Mautic\LeadBundle\Deduplicate\CompanyDeduper;
use Mautic\LeadBundle\Entity\CompanyRepository;
use Mautic\LeadBundle\Field\FieldList;
use Mautic\LeadBundle\Model\CompanyModel;
use Mautic\LeadBundle\Model\FieldModel;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class CompanyModelDecorated extends CompanyModel
{
    private bool $repositorySetUp = false;

    public function __construct(
        private CompanyModel $companyModel,
        FieldModel $leadFieldModel,
        EmailValidator $emailValidator,
        CompanyDeduper $companyDeduper,
        EntityManager $em,
        CorePermissions $security,
        EventDispatcherInterface $dispatcher,
        UrlGeneratorInterface $router,
        Translator $translator,
        UserHelper $userHelper,
        LoggerInterface $mauticLogger,
        CoreParametersHelper $coreParametersHelper,
        FieldList $fieldList,
    ) {
        parent::__construct($leadFieldModel, $emailValidator, $companyDeduper, $em, $security, $dispatcher, $router, $translator, $userHelper, $mauticLogger, $coreParametersHelper, $fieldList);
    }

    public function getRepository(): CompanyRepository
    {
        $repository = $this->companyModel->getRepository();

        if (!$this->repositorySetUp) {
            $this->repositorySetUp   = true;
            $defaultCommands         = $repository->getStandardSearchCommands();
            $availableSearchCommands = $repository->getSearchCommands();
            $companyCommands         = array_diff($availableSearchCommands, $defaultCommands);
            $companyCommands[]       = $this->translator->trans(CompanySegmentModel::SEARCH_COMMAND);

            $repository->setAvailableSearchFields($companyCommands);
        }

        return $repository;
    }
}
