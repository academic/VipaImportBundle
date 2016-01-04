<?php

namespace OkulBilisim\OjsImportBundle\Command;

use OkulBilisim\OjsImportBundle\Helper\ImportCommand;
use Ojs\CoreBundle\Helper\StringHelper;
use OkulBilisim\OjsImportBundle\Importer\PKP\AllJournalsImporter;
use OkulBilisim\OjsImportBundle\Importer\PKP\GivenJournalsImporter;
use OkulBilisim\OjsImportBundle\Importer\PKP\UserImporter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Stopwatch\Stopwatch;

class PkpGivenJournalsCommand extends ImportCommand
{
    protected function configure()
    {
        $this
            ->setName('ojs:import:pkp:given-journals')
            ->setDescription('Import given journals from PKP/OJS');

        parent::configure();
        $this->addArgument('ids', InputArgument::IS_ARRAY, 'Journal IDs (separate multiple IDs with a space)');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);
        $userManager = $this->getContainer()->get('fos_user.user_manager');
        $locale = $this->getContainer()->getParameter('locale');
        $tokenGenrator = $this->getContainer()->get('fos_user.util.token_generator');
        $userImporter = new UserImporter($this->connection, $this->em, $this->logger, $output, $userManager, $tokenGenrator, $locale);

        $stopwatch = new Stopwatch();
        $stopwatch->start('journals_import');

        $givenJournalsImporter = new GivenJournalsImporter($this->connection, $this->em, $this->logger, $output, $userImporter);
        $givenJournalsImporter->importJournals($input->getArgument('ids'));

        $event = $stopwatch->stop('journals_import');
        $output->writeln('Duration: ' . StringHelper::formatMilliseconds($event->getDuration()));
    }
}