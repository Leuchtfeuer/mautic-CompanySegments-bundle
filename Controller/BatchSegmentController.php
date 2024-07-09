<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Mautic\CoreBundle\Controller\AbstractFormController;
use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\CoreBundle\Factory\ModelFactory;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Service\FlashBag;
use Mautic\CoreBundle\Translation\Translator;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Form\Type\BatchType;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanySegmentActionModel;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanySegmentModel;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

class BatchSegmentController extends AbstractFormController
{
    public function __construct(
        private CompanySegmentActionModel $segmentActionModel,
        private CompanySegmentModel $segmentModel,
        ManagerRegistry $doctrine,
        MauticFactory $factory,
        ModelFactory $modelFactory,
        UserHelper $userHelper,
        CoreParametersHelper $coreParametersHelper,
        EventDispatcherInterface $dispatcher,
        Translator $translator,
        FlashBag $flashBag,
        RequestStack $requestStack,
        CorePermissions $security
    ) {
        parent::__construct($doctrine, $factory, $modelFactory, $userHelper, $coreParametersHelper, $dispatcher, $translator, $flashBag, $requestStack, $security);
    }

    /**
     * API for batch action.
     *
     * @see \Mautic\LeadBundle\Controller\BatchSegmentController::setAction
     */
    public function setAction(Request $request): JsonResponse
    {
        $params = $request->get('company_batch', []);
        \assert(is_array($params));

        $companyIds = '' === $params['ids'] ? [] : json_decode($params['ids'], true, 512, JSON_THROW_ON_ERROR);

        if ([] !== $companyIds && is_array($companyIds)) {
            $segmentsToAdd    = $params['add'] ?? [];
            $segmentsToRemove = $params['remove'] ?? [];

            if (is_array($segmentsToAdd) && [] !== $segmentsToAdd) {
                $this->segmentActionModel->addCompanies($companyIds, $segmentsToAdd);
            }

            if (is_array($segmentsToRemove) && [] !== $segmentsToRemove) {
                $this->segmentActionModel->removeCompanies($companyIds, $segmentsToRemove);
            }

            $this->addFlashMessage('mautic.company_segments.batch_companies_affected', [
                '%count%' => count($companyIds),
            ]);
        } else {
            $this->addFlashMessage('mautic.core.error.ids.missing');
        }

        return new JsonResponse([
            'closeModal' => true,
            'flashes'    => $this->getFlashContent(),
        ]);
    }

    /**
     * @see \Mautic\LeadBundle\Controller\BatchSegmentController::indexAction
     */
    public function indexAction(): Response
    {
        $route    = $this->generateUrl('mautic_company_segments_batch_company_set');
        $segments = $this->segmentModel->getCompanySegments();
        $items    = [];

        foreach ($segments as $segment) {
            $items[$segment['name']] = $segment['id'];
        }

        return $this->delegateView(
            [
                'viewParameters' => [
                    'form' => $this->createForm(
                        BatchType::class,
                        [],
                        [
                            'items'  => $items,
                            'action' => $route,
                        ]
                    )->createView(),
                ],
                'contentTemplate' => '@LeuchtfeuerCompanySegments/Batch/form.html.twig',
                'passthroughVars' => [
                    'activeLink'    => '#mautic_company_index',
                    'mauticContent' => 'companyBatch',
                    'route'         => $route,
                ],
            ]
        );
    }
}
