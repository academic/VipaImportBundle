<?php

namespace Ojs\ImportBundle\Command;

use Ojs\ImportBundle\Entity\ImportMap;
use Ojs\ImportBundle\Helper\ImportCommand;
use Ojs\ImportBundle\Importer\PKP\ArticleImporter;
use Ojs\ImportBundle\Importer\PKP\IssueImporter;
use Ojs\ImportBundle\Importer\PKP\UserImporter;
use Ojs\JournalBundle\Entity\Journal;
use Ojs\JournalBundle\Entity\Section;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class PkpIssueCommand extends ImportCommand
{
    protected function configure()
    {
        $this
            ->setName('ojs:import:pkp:issue')
            ->setDescription('Import an issue from PKP/OJS')
            ->addArgument('id', InputArgument::REQUIRED, 'Old issue ID')
            ->addArgument('journal', InputArgument::REQUIRED, 'New journal ID')
            ->addOption('without-articles', null, InputOption::VALUE_NONE, 'Exclude articles');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $um = $this->getContainer()->get('fos_user.user_manager');
        $locale = $this->getContainer()->getParameter('locale');
        $tokenGenrator = $this->getContainer()->get('fos_user.util.token_generator');

        $ui = new UserImporter($this->connection, $this->em, $this->logger, $output, $um, $tokenGenrator, $locale);
        $ii = new IssueImporter($this->connection, $this->em, $this->logger, $output);
        $ai = new ArticleImporter($this->connection, $this->em, $this->logger, $output, $ui);

        $oldIssueId = $input->getArgument('id');
        $journalId = $input->getArgument('journal');

        if (is_numeric($journalId)) {
            $journal = $this->em->find('OjsJournalBundle:Journal', $journalId);
        } else {
            $journal = $this->em->getRepository(Journal::class)->findOneBy(['slug' => $journalId]);
        }

        if (!$journal) {
            $output->writeln('<error>Journal does not exist.</error>');
            return;
        }

        $sectionRepo = $this->em->getRepository(Section::class);
        $currentSectionIds = array_column($sectionRepo->getIdsByJournal($journal), 'id');

        $ii->importIssues([['issue_id' => $oldIssueId]], $journal->getId(), $currentSectionIds);
        $mapRepo = $this->em->getRepository(ImportMap::class);

        if (!$input->getOption('without-articles')) {
            $sql = "SELECT article_id FROM published_articles WHERE issue_id = ?";
            $articleIds = $this->connection->executeQuery($sql, [$oldIssueId])->fetchAll();
            $oldSectionIds = $mapRepo->getSectionIds($journal);

            if (empty($oldSectionIds) && !empty($currentSectionIds)) {
                $oldSectionIds = $currentSectionIds[0];
            }

            $ai->importArticles(
                $articleIds,
                $journal->getId(),
                $oldSectionIds,
                $mapRepo->getIssueIds($journal)
            );
        }
    }
}
