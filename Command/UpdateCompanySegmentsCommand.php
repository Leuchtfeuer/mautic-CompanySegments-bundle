<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Command;

use Mautic\CoreBundle\Command\ModeratedCommand;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\CoreBundle\Helper\PathsHelper;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Entity\CompanySegment;
use MauticPlugin\LeuchtfeuerCompanySegmentsBundle\Model\CompanySegmentModel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class UpdateCompanySegmentsCommand extends ModeratedCommand
{
    protected static $defaultName        = 'leuchtfeuer:abm:segments-update';
    protected static $defaultDescription = 'Update companies in filter-based Company Segments based on new company data.';

    public function __construct(
        private CompanySegmentModel $companySegmentModel,
        private TranslatorInterface $translator,
        PathsHelper $pathsHelper,
        CoreParametersHelper $coreParametersHelper,
    ) {
        parent::__construct($pathsHelper, $coreParametersHelper);
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'batch-limit',
                'b',
                InputOption::VALUE_OPTIONAL,
                'Set batch size of companies to process per round. Defaults to 300.',
                300
            )
            ->addOption(
                'max-companies',
                'm',
                InputOption::VALUE_OPTIONAL,
                'Set max number of companies to process per company segment for this script execution. Defaults to all.',
                null
            )
            ->addOption(
                'segment-id',
                'i',
                InputOption::VALUE_OPTIONAL,
                'Specific ID to rebuild. Defaults to all.',
                null
            )
            ->addOption(
                'timing',
                'tm',
                InputOption::VALUE_NONE,
                'Measure timing of build with output to CLI .'
            )
            ->addOption(
                'exclude',
                'd',
                InputOption::VALUE_IS_ARRAY | InputOption::VALUE_OPTIONAL,
                'Exclude a specific company segment from being rebuilt. Otherwise, all company segments will be rebuilt.',
                []
            );

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $id                    = $input->getOption('segment-id');
        $batch                 = $input->getOption('batch-limit');
        $max                   = $input->getOption('max-companies');
        $enableTimeMeasurement = (bool) $input->getOption('timing');
        $output                = true === $input->getOption('quiet') ? new NullOutput() : $output;
        $excludeSegments       = $input->getOption('exclude');

        if (null !== $id && !(is_numeric($id) && $id > 0)) {
            $output->writeln('<error>The --segment-id option must be a positive number or none.</error>');

            return self::FAILURE;
        }

        if (!is_numeric($batch) || $batch < 1) {
            $output->writeln('<error>The --batch-limit option must be a positive number.</error>');

            return self::FAILURE;
        }

        $batch = (int) $batch;

        if (null !== $max && !(is_numeric($max) && $max > 0)) {
            $output->writeln('<error>The --max-companies option must be a positive number or none.</error>');

            return self::FAILURE;
        }

        $max = null !== $max ? (int) $max : null;

        if (!$this->checkRunStatus($input, $output, $id)) {
            return Command::SUCCESS;
        }

        if ($enableTimeMeasurement) {
            $startTime = microtime(true);
        }

        if (null !== $id) {
            $segment = $this->companySegmentModel->getEntity($id);

            if (!$segment instanceof CompanySegment) {
                $output->writeln('<error>'.$this->translator->trans('mautic.company_segments.list.rebuild.not_found', ['%id%' => $id]).'</error>');

                return Command::FAILURE;
            }

            $this->rebuildSegment($segment, $batch, $max, $output);
        } else {
            $filter = [
                'iterable_mode' => true,
            ];

            if (is_array($excludeSegments) && count($excludeSegments) > 0) {
                $filter['filter'] = [
                    'force' => [
                        [
                            'expr'   => 'notIn',
                            'column' => $this->companySegmentModel->getRepository()->getTableAlias().'.id',
                            'value'  => $excludeSegments,
                        ],
                    ],
                ];
            }
            $companySegments = $this->companySegmentModel->getEntities($filter);

            foreach ($companySegments as $companySegment) {
                $startTimeForSingleSegment = time();
                $this->rebuildSegment($companySegment, $batch, $max, $output);
                if ($enableTimeMeasurement) {
                    $totalTime = round(microtime(true) - $startTimeForSingleSegment, 2);
                    $output->writeln('<fg=cyan>'.$this->translator->trans('mautic.company_segments.rebuild.contacts.time', ['%time%' => $totalTime]).'</>'."\n");
                }
                unset($companySegment);
            }
            unset($companySegments);
        }

        $this->completeRun();

        if ($enableTimeMeasurement) {
            $totalTime = round(microtime(true) - $startTime, 2);
            $output->writeln('<fg=magenta>'.$this->translator->trans('mautic.company_segments.rebuild.total.time', ['%time%' => $totalTime]).'</>'."\n");
        }

        return Command::SUCCESS;
    }

    private function rebuildSegment(CompanySegment $companySegment, int $batch, ?int $max, OutputInterface $output): void
    {
        if (!$companySegment->isPublished()) {
            return;
        }

        $output->writeln('<info>'.$this->translator->trans('mautic.company_segments.rebuild.rebuilding', ['%id%' => $companySegment->getId()]).'</info>');
        $startTime   = microtime(true);
        $processed   = $this->companySegmentModel->rebuildCompanySegment($companySegment, $batch, $max, $output);
        $rebuildTime = round(microtime(true) - $startTime, 2);
        if (null === $max) {
            // Only full segment rebuilds count
            $companySegment->setLastBuiltDateToCurrentDatetime();
            $companySegment->setLastBuiltTime($rebuildTime);
            $this->companySegmentModel->saveEntity($companySegment);
        }

        $this->companySegmentModel->getRepository()->detachEntity($companySegment);

        $output->writeln(
            '<comment>'.$this->translator->trans('mautic.company_segments.rebuild.companies_affected', ['%companies%' => $processed]).'</comment>'
        );
    }
}
