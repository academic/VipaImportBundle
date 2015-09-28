<?php

namespace Okulbilisim\OjsImportBundle\Command;

use Okulbilisim\OjsImportBundle\Helper\ImportCommand;
use Okulbilisim\OjsImportBundle\Importer\PKP\JournalImporter;
use Okulbilisim\OjsImportBundle\Importer\PKP\JournalUserImporter;
use Okulbilisim\OjsImportBundle\Importer\PKP\UserImporter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
        $userImporter = new UserImporter($this->connection, $this->em, $userManager, $tokenGenrator, $locale);

        $journalImporter = new JournalImporter($this->connection, $this->em, $userImporter);
        $ids = $journalImporter->importJournal($input->getArgument('id'));

        $journalUserImporter = new JournalUserImporter($this->connection, $this->em);
        $journalUserImporter->importJournalUsers($ids['new'], $ids['old'], $userImporter);
    }
}