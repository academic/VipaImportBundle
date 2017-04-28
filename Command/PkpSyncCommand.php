<?php

namespace Vipa\ImportBundle\Command;

use Doctrine\DBAL\Connection;
use Vipa\ImportBundle\Entity\ImportMap;
use Vipa\ImportBundle\Helper\ImportCommand;
use Vipa\ImportBundle\Importer\PKP\ArticleImporter;
use Vipa\ImportBundle\Importer\PKP\IssueImporter;
use Vipa\ImportBundle\Importer\PKP\UserImporter;
use Vipa\JournalBundle\Entity\Article;
use Vipa\JournalBundle\Entity\Issue;
use Vipa\JournalBundle\Entity\Journal;
use Vipa\JournalBundle\Entity\Section;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PkpSyncCommand extends ImportCommand
{
    /** @var UserImporter */
    protected $ui;

    protected function configure()
    {
        $this->setName('ojs:import:pkp:sync');
        parent::configure();
    }
    
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $locale = $this->getContainer()->getParameter('locale');
        $um = $this->getContainer()->get('fos_user.user_manager');
        $tokenGenrator = $this->getContainer()->get('fos_user.util.token_generator');
        $this->ui = new UserImporter($this->connection, $this->em, $this->logger, $output, $um, $tokenGenrator, $locale);

        $this->syncIssues($output);
        $this->syncArticles($output);
    }

    /**
     * @param OutputInterface $output
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    protected function syncIssues(OutputInterface $output)
    {
        $ii = new IssueImporter($this->connection, $this->em, $this->logger, $output, $this->ui);

        $importRepo = $this->em->getRepository(ImportMap::class);
        $journalMaps = $importRepo->findBy(['type' => Journal::class]);
        $createdIssueIds = [];

        /** @var ImportMap $journalMap */
        foreach ($journalMaps as $journalMap) {
            $output->writeln('Synchronizing #'.$journalMap->getNewId());
            $journalRef = $this->em->getReference(Journal::class, $journalMap->getNewId());

            $importedIssueIds = null;
            $importedIssues = array_values(
                $this->em
                    ->createQueryBuilder()
                    ->select('map.oldId')
                    ->from(ImportMap::class, 'map')
                    ->join(Issue::class, 'issue', 'WITH', 'map.newId = issue.id')
                    ->where('map.type = :type')
                    ->andWhere('issue.journal = :journal')
                    ->setParameter('type', Issue::class)
                    ->setParameter('journal', $journalRef)
                    ->getQuery()->getScalarResult()
            );


            foreach ($importedIssues as $key => $importedIssue) {
                $importedIssueIds[] = $importedIssue['oldId'];
            }

            $issuesSql = 'SELECT issue_id FROM issues WHERE issue_id NOT IN (?) AND journal_id = ?';
            $issuesStatement = $this->connection->executeQuery(
                $issuesSql,
                [$importedIssueIds, [$journalMap->getOldId()]],
                [Connection::PARAM_INT_ARRAY, Connection::PARAM_INT_ARRAY]
            );

            $nonImportedIssues = $issuesStatement->fetchAll();
            $importedSectionIds = $this->getSectionIds($journalRef);
            $createdIssueIds = $ii->importIssues($nonImportedIssues, $journalMap->getNewId(), $importedSectionIds);
        }

        return $createdIssueIds;
    }

    /**
     * @param OutputInterface $output
     * @return array
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     * @throws \Exception
     */
    protected function syncArticles(OutputInterface $output)
    {
        $ai = new ArticleImporter($this->connection, $this->em, $this->logger, $output, $this->ui);

        $importRepo = $this->em->getRepository(ImportMap::class);
        $journalMaps = $importRepo->findBy(['type' => Journal::class]);

        /** @var ImportMap $journalMap */
        foreach ($journalMaps as $journalMap) {
            $output->writeln('Synchronizing #'.$journalMap->getNewId());
            $journalRef = $this->em->getReference(Journal::class, $journalMap->getNewId());

            $importedArticleIds = null;
            $importedArticles = array_values(
                $this->em
                    ->createQueryBuilder()
                    ->select('map.oldId')
                    ->from(ImportMap::class, 'map')
                    ->join(Article::class, 'article', 'WITH', 'map.newId = article.id')
                    ->where('map.type = :type')
                    ->andWhere('article.journal = :journal')
                    ->setParameter('type', Article::class)
                    ->setParameter('journal', $journalRef)
                    ->getQuery()->getScalarResult()
            );


            foreach ($importedArticles as $key => $importedArticle) {
                $importedArticleIds[] = $importedArticle['oldId'];
            }

            $articlesSql = 'SELECT article_id FROM articles WHERE article_id NOT IN (?) AND journal_id = ?';
            $articlesStatement = $this->connection->executeQuery(
                $articlesSql,
                [$importedArticleIds, [$journalMap->getOldId()]],
                [Connection::PARAM_INT_ARRAY, Connection::PARAM_INT_ARRAY]
            );

            $nonImportedArticles = $articlesStatement->fetchAll();
            $importedSectionIds = $this->getSectionIds($journalRef);
            $ai->importArticles(
                $nonImportedArticles,
                $journalMap->getNewId(),
                $importedSectionIds,
                $this->getIssueIds($journalRef)
            );
        }
    }

    /**
     * @param $journal
     * @return array|null
     */
    protected function getSectionIds($journal)
    {
        $importedSectionIds = null;
        $importedSections = array_values(
            $this->em
                ->createQueryBuilder()
                ->select('map.oldId, map.newId')
                ->from(ImportMap::class, 'map')
                ->join(Section::class, 'section', 'WITH', 'map.newId = section.id')
                ->where('map.type = :type')
                ->andWhere('section.journal = :journal')
                ->setParameter('type', Section::class)
                ->setParameter('journal', $journal)
                ->getQuery()->getScalarResult()
        );

        foreach ($importedSections as $key => $importedSection) {
            $importedSectionIds[$importedSection['oldId']] = $importedSection['newId'];
        }

        return $importedSectionIds;
    }

    /**
     * @param $journal
     * @return array|null
     */
    protected function getIssueIds($journal)
    {
        $importedIssueIds = null;
        $importedIssues = array_values(
            $this->em
                ->createQueryBuilder()
                ->select('map.oldId, map.newId')
                ->from(ImportMap::class, 'map')
                ->join(Issue::class, 'issue', 'WITH', 'map.newId = issue.id')
                ->where('map.type = :type')
                ->andWhere('issue.journal = :journal')
                ->setParameter('type', Issue::class)
                ->setParameter('journal', $journal)
                ->getQuery()->getScalarResult()
        );

        foreach ($importedIssues as $key => $importedIssue) {
            $importedIssueIds[$importedIssue['oldId']] = $importedIssue['newId'];
        }

        return $importedIssueIds;
    }
}
