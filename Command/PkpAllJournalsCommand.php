<?php

namespace OkulBilisim\OjsImportBundle\Command;

use OkulBilisim\OjsImportBundle\Helper\ImportCommand;
use Ojs\CoreBundle\Helper\StringHelper;
use OkulBilisim\OjsImportBundle\Importer\PKP\AllJournalsImporter;
use OkulBilisim\OjsImportBundle\Importer\PKP\UserImporter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Stopwatch\Stopwatch;

class PkpAllJournalsCommand extends ImportCommand
{
    protected function configure()
    {
        $this
            ->setName('ojs:import:pkp:all-journals')
            ->setDescription('Import all journals from PKP/OJS');

        parent::configure();
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

        $allJournalsImporter = new AllJournalsImporter($this->connection, $this->em, $this->logger, $output, $userImporter);
        $allJournalsImporter->importJournals();

        $event = $stopwatch->stop('journals_import');
        $output->writeln('Duration: ' . StringHelper::formatMilliseconds($event->getDuration()));
    }
}