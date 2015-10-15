<?php

namespace OkulBilisim\OjsImportBundle\Command;

use Ojs\CoreBundle\Helper\StringHelper;
use OkulBilisim\OjsImportBundle\Helper\ImportCommand;
use OkulBilisim\OjsImportBundle\Importer\PKP\JournalImporter;
use OkulBilisim\OjsImportBundle\Importer\PKP\JournalUserImporter;
use OkulBilisim\OjsImportBundle\Importer\PKP\UserImporter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Stopwatch\Stopwatch;

class PkpJournalCommand extends ImportCommand
{
    protected function configure()
    {
        $this
            ->setName('ojs:import:pkp:journal')
            ->setDescription('Import an user from PKP/OJS')
            ->addArgument('id', InputArgument::REQUIRED, 'Journal ID');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);
        $userManager = $this->getContainer()->get('fos_user.user_manager');
        $locale = $this->getContainer()->getParameter('locale');
        $tokenGenrator = $this->getContainer()->get('fos_user.util.token_generator');
        $userImporter = new UserImporter($this->connection, $this->em, $output, $userManager, $tokenGenrator, $locale);

        $stopwatch = new Stopwatch();
        $stopwatch->start('journal_import');

        $journalImporter = new JournalImporter($this->connection, $this->em, $output, $userImporter);
        $ids = $journalImporter->importJournal($input->getArgument('id'));

        $journalUserImporter = new JournalUserImporter($this->connection, $this->em, $output);
        $journalUserImporter->importJournalUsers($ids['new'], $ids['old'], $userImporter);

        $event = $stopwatch->stop('journal_import');
        $output->write('Duration: ' . StringHelper::formatMilliseconds($event->getDuration()));
    }
}