<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\EventListener;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Mautic\LeadBundle\Entity\CompanyRepository;
use Mautic\LeadBundle\Event\CompanyBuildSearchEvent;
use Mautic\LeadBundle\LeadEvents;
use Mautic\LeadBundle\Segment\Query\QueryBuilder;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegment;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanySegmentModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class CompanySearchSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private TranslatorInterface $translator,
        private CompanyRepository $companyRepository,
        private Connection $connection,
    ) {
    }

    /**
     * @see \Mautic\LeadBundle\Entity\LeadRepository::addSearchCommandWhereClause mautic.lead.lead.searchcommand.list for copied implementation
     * @see \Mautic\LeadBundle\Entity\CompanyRepository::addSearchCommandWhereClause where the event is executed.
     * @see \Mautic\CoreBundle\Entity\CommonRepository::parseSearchFilters where the event is checked and executed.
     */
    public function onBuildSearch(CompanyBuildSearchEvent $event): void
    {
        if ($event->getCommand() !== $this->translator->trans(CompanySegmentModel::SEARCH_COMMAND)) {
            return;
        }

        $companySegmentIds = $this->getCompanySegmentIdsByAlias($event->getString());

        if ([] === $companySegmentIds) {
            return;
        }

        $uniqueParameterAlias = $event->getAlias();

        $sq = (new QueryBuilder($this->connection));
        $sq->select('1')
            ->from(MAUTIC_TABLE_PREFIX.CompanySegment::RELATION_TABLE_NAME, CompanySegment::DEFAULT_RELATIONS_ALIAS)
            ->where(
                $sq->expr()->and(
                    $sq->expr()->eq(
                        $this->companyRepository->getTableAlias().'.id',
                        CompanySegment::DEFAULT_RELATIONS_ALIAS.'.company_id'
                    ),
                    $sq->expr()->in(CompanySegment::DEFAULT_RELATIONS_ALIAS.'.segment_id', ':'.$uniqueParameterAlias)
                )
            );

        // do not escape the subquery.
        $event->setStrict(true);
        // return the expression of query builder.
        $event->setReturnParameters(false);
        if ($event->isNegation()) {
            $event->setSubQuery($sq->expr()->notExists($sq->getSQL()));
        } else {
            $event->setSubQuery($sq->expr()->exists($sq->getSQL()));
        }

        // must set parameter directly to query builder, because setting to $event->setParameter
        // will lead to IN("id1, id2")
        $event->getQueryBuilder()->setParameter(
            $uniqueParameterAlias,
            $companySegmentIds,
            ArrayParameterType::INTEGER
        );

        // set the event as finished, so the query cn be executed.
        $event->setSearchStatus(true);
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            LeadEvents::COMPANY_BUILD_SEARCH_COMMANDS => 'onBuildSearch',
        ];
    }

    /**
     * @return array<int, int>
     */
    private function getCompanySegmentIdsByAlias(string $segmentAlias): array
    {
        $result = (new QueryBuilder($this->connection))
            ->select(CompanySegment::DEFAULT_ALIAS.'.id')
            ->from(MAUTIC_TABLE_PREFIX.CompanySegment::TABLE_NAME, CompanySegment::DEFAULT_ALIAS)
            ->where(CompanySegment::DEFAULT_ALIAS.'.alias = :alias')
            ->setParameter('alias', $segmentAlias)
            ->executeQuery()
            ->fetchFirstColumn();

        $return = [];
        foreach ($result as $index => $id) {
            if (!is_numeric($id)) {
                throw new \RuntimeException('The ID is not numeric.');
            }

            $return[$index] = (int) $id;
        }

        return $return;
    }
}
