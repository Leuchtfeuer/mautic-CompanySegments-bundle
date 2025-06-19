<?php

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Controller;

use Doctrine\Persistence\ManagerRegistry;
use Mautic\CoreBundle\Controller\CommonController;
use Mautic\CoreBundle\Factory\MauticFactory;
use Mautic\CoreBundle\Factory\ModelFactory;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\InputHelper;
use Mautic\CoreBundle\Helper\UserHelper;
use Mautic\CoreBundle\Security\Permissions\CorePermissions;
use Mautic\CoreBundle\Service\FlashBag;
use Mautic\CoreBundle\Translation\Translator;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;

class CompanyTimelineController extends CommonController
{
    use CompanyAccessTrait;
    use CompanyDetailsTrait;

    private RequestStack $requestStack;

    public function __construct(
        ManagerRegistry $doctrine,
        MauticFactory $factory,
        ModelFactory $modelFactory,
        UserHelper $userHelper,
        CoreParametersHelper $coreParametersHelper,
        EventDispatcherInterface $eventDispatcher,
        Translator $translator,
        FlashBag $flashBag,
        RequestStack $requestStack,
        CorePermissions $security,
    ) {
        parent::__construct(
            $doctrine,
            $factory,
            $modelFactory,
            $userHelper,
            $coreParametersHelper,
            $eventDispatcher,
            $translator,
            $flashBag,
            $requestStack,
            $security
        );
        $this->requestStack = $requestStack;
    }

    public function indexAction(Request $request, ?string $companyId, $page = 1)
    {
        if (empty($companyId)) {
            return $this->accessDenied();
        }

        $company = $this->checkLeadAccess($companyId, 'view');
        if ($company instanceof Response) {
            return $company;
        }

        $this->setListFilters();

        $session = $request->getSession();
        if ('POST' == $request->getMethod() && $request->request->has('search')) {
            $filters = [
                'search'        => InputHelper::clean($request->request->get('search')),
                'includeEvents' => InputHelper::clean($request->request->get('includeEvents') ?? []),
                'excludeEvents' => InputHelper::clean($request->request->get('excludeEvents') ?? []),
            ];
            $session->set('mautic.company.'.$companyId.'.timeline.filters', $filters);
        } else {
            $filters = null;
        }

        $order = [
            $session->get('mautic.company.'.$companyId.'.timeline.orderby'),
            $session->get('mautic.company.'.$companyId.'.timeline.orderbydir'),
        ];

        $events = $this->getEngagements($company, $filters, $order, $page);

        return $this->delegateView(
            [
                'viewParameters' => [
                    'company' => $company,
                    'page'    => $page,
                    'events'  => $events,
                ],
                'passthroughVars' => [
                    'route'         => false,
                    'timelineCount' => $events['total'],
                ],
                'contentTemplate' => '@LeuchtfeuerCompanySegments/Timeline/_list.html.twig',
            ]
        );
    }

    public function pluginIndexAction(Request $request, $integration, $page = 1)
    {
        $limit     = 25;
        $companies = $this->checkAllAccess('view', $limit);

        if ($companies instanceof Response) {
            return $companies;
        }

        $this->setListFilters();

        $session = $request->getSession();
        if ('POST' === $request->getMethod() && $request->request->has('search')) {
            $filters = [
                'search'        => InputHelper::clean($request->request->get('search')),
                'includeEvents' => InputHelper::clean($request->request->get('includeEvents') ?? []),
                'excludeEvents' => InputHelper::clean($request->request->get('excludeEvents') ?? []),
            ];
            $session->set('mautic.plugin.company.timeline.filters', $filters);
        } else {
            $filters = null;
        }

        $order = [
            $session->get('mautic.plugin.company.timeline.orderby'),
            $session->get('mautic.plugin.company.timeline.orderbydir'),
        ];

        // get all events grouped by company
        $events = $this->getAllEngagements($companies, $filters, $order, $page, $limit);

        $str = $request->server->get('QUERY_STRING');
        parse_str($str, $query);

        $tmpl = 'table';
        if (array_key_exists('from', $query) && 'iframe' === $query['from']) {
            $tmpl = 'list';
        }
        if (array_key_exists('tmpl', $query)) {
            $tmpl = $query['tmpl'];
        }

        return $this->delegateView(
            [
                'viewParameters' => [
                    'leads'       => $companies,
                    'page'        => $page,
                    'events'      => $events,
                    'integration' => $integration,
                    'tmpl'        => (!$request->isXmlHttpRequest()) ? 'index' : '',
                    'newCount'    => (array_key_exists('count', $query) && $query['count']) ? $query['count'] : 0,
                ],
                'passthroughVars' => [
                    'route'         => false,
                    'mauticContent' => 'pluginTimeline',
                    'timelineCount' => $events['total'],
                ],
                'contentTemplate' => sprintf('@LeuchtfeuerCompanySegments/Timeline/plugin_%s.html.twig', $tmpl),
            ]
        );
    }

    public function pluginViewAction(Request $request, $integration, ?string $companyId, $page = 1)
    {
        if (empty($companyId)) {
            return $this->notFound();
        }

        $company = $this->checkLeadAccess($companyId, 'view', true, $integration);
        if ($company instanceof Response) {
            return $company;
        }

        $this->setListFilters();

        $session = $request->getSession();
        if ('POST' === $request->getMethod() && $request->request->has('search')) {
            $filters = [
                'search'        => InputHelper::clean($request->request->get('search')),
                'includeEvents' => InputHelper::clean($request->request->get('includeEvents') ?? []),
                'excludeEvents' => InputHelper::clean($request->request->get('excludeEvents') ?? []),
            ];
            $session->set('mautic.plugin.company.timeline.'.$companyId.'.filters', $filters);
        } else {
            $filters = null;
        }

        $order = [
            $session->get('mautic.plugin.company.timeline.'.$companyId.'.orderby'),
            $session->get('mautic.plugin.company.timeline.'.$companyId.'.orderbydir'),
        ];

        $events = $this->getEngagements($company, $filters, $order, $page);

        $str = $request->server->get('QUERY_STRING');
        parse_str($str, $query);

        $tmpl = 'table';
        if (array_key_exists('from', $query) && 'iframe' === $query['from']) {
            $tmpl = 'list';
        }
        if (array_key_exists('tmpl', $query)) {
            $tmpl = $query['tmpl'];
        }

        return $this->delegateView(
            [
                'viewParameters' => [
                    'company'     => $company,
                    'page'        => $page,
                    'integration' => $integration,
                    'events'      => $events,
                    'newCount'    => (array_key_exists('count', $query) && $query['count']) ? $query['count'] : 0,
                ],
                'passthroughVars' => [
                    'route'         => false,
                    'mauticContent' => 'pluginTimeline',
                    'timelineCount' => $events['total'],
                ],
                'contentTemplate' => sprintf('@LeuchtfeuerCompanySegments/Timeline/plugin_%s.html.twig', $tmpl),
            ]
        );
    }
}
