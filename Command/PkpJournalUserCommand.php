<?php

namespace Ojs\ImportBundle\Command;

use Ojs\ImportBundle\Helper\ImportCommand;
use Ojs\ImportBundle\Importer\PKP\JournalUserImporter;
use Ojs\ImportBundle\Importer\PKP\UserImporter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PkpJournalUserCommand extends ImportCommand
{
    protected function configure()
    {
        $this
            ->setName('ojs:import:pkp:journal-user')
            ->setDescription('Import a journal\'s users from PKP/OJS')
            ->addArgument('oldId', InputArgument::REQUIRED, 'Old journal ID')
            ->addArgument('newId', InputArgument::REQUIRED, 'New journal ID');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $oldId = $input->getArgument('oldId');
        $newId = $input->getArgument('newId');
        $locale = $this->getContainer()->getParameter('locale');
        $um = $this->getContainer()->get('fos_user.user_manager');
        $tokenGenrator = $this->getContainer()->get('fos_user.util.token_generator');
        $userImporter = new UserImporter($this->connection, $this->em, $this->logger, $output, $um, $tokenGenrator, $locale);

        $journalUserImporter = new JournalUserImporter($this->connection, $this->em, $this->logger, $output);
        $journalUserImporter->importJournalUsers($newId, $oldId, $userImporter);
    }
}
