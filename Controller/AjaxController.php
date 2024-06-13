<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Controller;

use Mautic\CoreBundle\Controller\AjaxController as CommonAjaxController;
use Mautic\CoreBundle\Helper\InputHelper;
use Mautic\LeadBundle\Form\Type\FilterPropertiesType;
use Mautic\LeadBundle\Provider\FormAdjustmentsProviderInterface;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanySegmentModel;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class AjaxController extends CommonAjaxController
{
    public function loadCompanySegmentFilterFormAction(
        Request $request,
        FormFactoryInterface $formFactory,
        FormAdjustmentsProviderInterface $formAdjustmentsProvider,
        CompanySegmentModel $companySegmentModel
    ): JsonResponse {
        $fieldAlias  = InputHelper::clean($request->request->get('fieldAlias'));
        $fieldObject = InputHelper::clean($request->request->get('fieldObject'));
        $operator    = InputHelper::clean($request->request->get('operator'));
        $filterNum   = (int) $request->request->get('filterNum');

        if (!is_string($fieldAlias) || !is_string($fieldObject) || !is_string($operator)) {
            throw new BadRequestHttpException();
        }

        $form = $formFactory->createNamed('RENAME', FilterPropertiesType::class);

        if ('' !== $fieldAlias && '' !== $operator) {
            $formAdjustmentsProvider->adjustForm(
                $form,
                $fieldAlias,
                $fieldObject,
                $operator,
                $companySegmentModel->getChoiceFields()[$fieldObject][$fieldAlias]
            );
        }

        $formHtml = $this->renderView(
            '@MauticLead/List/filterpropform.html.twig',
            [
                'form' => $form->createView(),
            ]
        );

        $formHtml = str_replace('id="RENAME', "id=\"company_segments_filters_{$filterNum}_properties", $formHtml);
        $formHtml = str_replace('name="RENAME', "name=\"company_segments[filters][{$filterNum}][properties]", $formHtml);

        return $this->sendJsonResponse(
            [
                'viewParameters' => [
                    'form' => $formHtml,
                ],
            ]
        );
    }

    public function getCompaniesCountAction(Request $request, CompanySegmentModel $companySegmentModel): JsonResponse
    {
        $id = InputHelper::clean($request->get('id'));
        $id = is_numeric($id) ? (int) $id : 0;

        $companyExists = 1 === $companySegmentModel->getRepository()->count(['id' => $id]);

        if (!$companyExists) {
            return new JsonResponse($this->prepareJsonResponse(0), Response::HTTP_NOT_FOUND);
        }

        $companyCounts = $companySegmentModel->getSegmentCompanyCountFromCache([$id]);
        $companyCount  = $companyCounts[$id];

        return new JsonResponse($this->prepareJsonResponse($companyCount));
    }

    /**
     * @return array<string, mixed>
     */
    private function prepareJsonResponse(int $companyCount): array
    {
        return [
            'html' => $this->translator->trans(
                'mautic.company_segments.companies_count',
                ['%count%' => $companyCount]
            ),
            'companyCount' => $companyCount,
        ];
    }
}
