<?php

namespace Okulbilisim\OjsImportBundle\Command;

use Okulbilisim\OjsImportBundle\Helper\ImportCommand;
use Okulbilisim\OjsImportBundle\Importer\PKP\JournalImporter;
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
        $importer = new JournalImporter($this->connection, $this->em);
        $importer->importJournal($input->getArgument('id'));
    }
}