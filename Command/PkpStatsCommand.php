<?php

namespace Vipa\ImportBundle\Command;


use Vipa\CoreBundle\Helper\StringHelper;
use Vipa\ImportBundle\Helper\ImportCommand;
use Vipa\ImportBundle\Importer\PKP\ArticleStatisticImporter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Stopwatch\Stopwatch;

class PkpStatsCommand extends ImportCommand
{
    protected function configure()
    {
        $this
            ->setName('ojs:import:pkp:stats')
            ->setDescription('Import article stats from PKP/OJS');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);
        $importer = new ArticleStatisticImporter($this->connection, $this->em,  $this->logger, $output);

        $stopwatch = new Stopwatch();
        $stopwatch->start('stats_import');
        $importer->importArticleStatistics();

        $event = $stopwatch->stop('stats_import');
        $output->writeln('Duration: ' . StringHelper::formatMilliseconds($event->getDuration()));
    }
}
