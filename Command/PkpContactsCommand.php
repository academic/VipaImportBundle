<?php

namespace Ojs\ImportBundle\Command;

use Ojs\CoreBundle\Helper\StringHelper;
use Ojs\ImportBundle\Helper\ImportCommand;
use Ojs\ImportBundle\Importer\PKP\JournalContactImporter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Stopwatch\Stopwatch;

class PkpContactsCommand extends ImportCommand
{
    protected function configure()
    {
        $this
            ->setName('ojs:import:pkp:contacts')
            ->setDescription('Import journal contacts from PKP/OJS');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);
        $importer = new JournalContactImporter($this->connection, $this->em,  $this->logger, $output);

        $journalsSql = 'SELECT journal_id, path FROM journals';
        $journalsStatement = $this->connection->executeQuery($journalsSql);
        $journals = $journalsStatement->fetchAll();

        $stopwatch = new Stopwatch();
        $stopwatch->start('stats_import');

        foreach ($journals as $journal) {
            $existingJournal = $this->em
                ->getRepository('OjsJournalBundle:Journal')
                ->findOneBy(['slug' => $journal['path']]);

            if($existingJournal !== null && $existingJournal->getJournalContacts()->isEmpty()) {
                $importer->importContacts($existingJournal, $journal['journal_id']);
                $this->em->persist($existingJournal);
                $this->em->flush();
            }
        }

        $event = $stopwatch->stop('stats_import');
        $output->writeln('Duration: ' . StringHelper::formatMilliseconds($event->getDuration()));
    }
}
