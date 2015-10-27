<?php

namespace OkulBilisim\OjsImportBundle\Importer\PKP;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Exception;
use OkulBilisim\OjsImportBundle\Importer\Importer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;

class GivenJournalsImporter extends Importer
{
    /** @var UserImporter */
    private $ui;

    /**
     * AllJournalsImporter constructor.
     * @param Connection $connection
     * @param EntityManager $em
     * @param LoggerInterface $logger
     * @param OutputInterface $consoleOutput
     * @param UserImporter $ui
     */
    public function __construct(
        Connection $connection,
        EntityManager $em,
        LoggerInterface $logger,
        OutputInterface $consoleOutput,
        UserImporter $ui
    )
    {
        parent::__construct($connection, $em, $logger, $consoleOutput);
        $this->ui = $ui;
    }

    public function importJournals($ids)
    {
        $journalsSql = 'SELECT journal_id, path FROM journals WHERE journal_id IN (?)';
        $journalsStatement = $this->connection->executeQuery($journalsSql, array($ids), array(Connection::PARAM_INT_ARRAY));
        $journals = $journalsStatement->fetchAll();

        $journalImporter = new JournalImporter(
            $this->connection, $this->em, $this->logger, $this->consoleOutput, $this->ui
        );

        foreach ($journals as $journal) {
            $existingJournal = $this->em
                ->getRepository('OjsJournalBundle:Journal')
                ->findOneBy(['slug' => $journal['path']]);

            if (!$existingJournal) {
                try {
                    $ids = $journalImporter->importJournal($journal['journal_id']);
                    $journalUserImporter = new JournalUserImporter($this->connection, $this->em, $this->logger, $this->consoleOutput);
                    $journalUserImporter->importJournalUsers($ids['new'], $ids['old'], $this->ui);
                } catch (Exception $exception) {
                    $message = sprintf(
                        '%s: %s (uncaught exception) at %s line %s',
                        get_class($exception),
                        $exception->getMessage(),
                        $exception->getFile(),
                        $exception->getLine()
                    );

                    $this->consoleOutput->writeln('Importing of journal #' . $journal['journal_id'] . 'failed.');
                    $this->consoleOutput->writeln($message);
                    $this->consoleOutput->writeln($exception->getTraceAsString());
                }
            } else {
                $this->consoleOutput->writeln('Journal #' . $journal['journal_id'] . ' already imported. Skipped.');
            }
        }
    }

}