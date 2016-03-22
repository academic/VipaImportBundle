<?php

namespace Ojs\ImportBundle\Importer\PKP;

use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Exception;
use Ojs\CoreBundle\Params\ArticleStatuses;
use Ojs\JournalBundle\Entity\Article;
use Ojs\JournalBundle\Entity\ArticleAuthor;
use Ojs\JournalBundle\Entity\Author;
use Ojs\JournalBundle\Entity\Citation;
use Ojs\JournalBundle\Entity\Issue;
use Ojs\JournalBundle\Entity\Journal;
use Ojs\JournalBundle\Entity\Section;
use Ojs\ImportBundle\Entity\PendingStatisticImport;
use Ojs\ImportBundle\Entity\PendingSubmitterImport;
use Ojs\ImportBundle\Helper\StringHelper;
use Ojs\ImportBundle\Importer\Importer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ArticleImporter extends Importer
{
    /**
     * @var UserImporter
     */
    private $ui;

    /**
     * ArticleImporter constructor.
     * @param Connection $dbalConnection
     * @param EntityManager $em
     * @param OutputInterface $consoleOutput
     * @param LoggerInterface $logger
     * @param UserImporter $ui
     */
    public function __construct(
        Connection $dbalConnection,
        EntityManager $em,
        LoggerInterface $logger,
        OutputInterface $consoleOutput,
        UserImporter $ui
    )
    {
        parent::__construct($dbalConnection, $em, $logger, $consoleOutput);
        $this->ui = $ui;
    }

    /**
     * Imports the articles of given Journal.
     * @param int $oldJournalId Old journal's ID
     * @param int $newJournalId New journal's ID
     * @param array $issueIds   Issue IDs of journal
     * @param array $sectionIds Section IDs of journal
     * @throws Exception
     * @throws \Doctrine\DBAL\ConnectionException
     * @throws \Doctrine\DBAL\DBALException
     */
    public function importArticles($oldJournalId, $newJournalId, $issueIds, $sectionIds)
    {
        $articleSql = "SELECT article_id FROM articles WHERE journal_id = :journal_id";
        $articleStatement = $this->dbalConnection->prepare($articleSql);
        $articleStatement->bindValue('journal_id', $oldJournalId);
        $articleStatement->execute();
        $articles = $articleStatement->fetchAll();

        try {
            $this->em->beginTransaction();
            $persistCounter = 1;

            foreach ($articles as $article) {
                $this->importArticle($article['article_id'], $newJournalId, $issueIds, $sectionIds);
                $persistCounter++;

                if ($persistCounter % 10 == 0  || $persistCounter == count($articles)) {
                    $this->consoleOutput->writeln("Writing articles...", true);
                    $this->em->flush();
                    $this->em->commit();
                    $this->em->clear();
                    $this->em->beginTransaction();
                }
            }

            $this->em->commit();
        } catch (Exception $exception) {
            $this->em->rollback();
            throw $exception;
        }
    }

    /**
     * Imports the given article.
     * @param int $id Article's ID
     * @param int $newJournalId New journal's ID
     * @param array $issueIds   IDs of issues
     * @param array $sectionIds IDs of sections
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    private function importArticle($id, $newJournalId, $issueIds, $sectionIds)
    {
        /** @var Journal $journal */
        $journal = $this->em->getReference('OjsJournalBundle:Journal', $newJournalId);
        $this->consoleOutput->writeln("Reading article #" . $id . "... ", true);

        $articleSql = "SELECT articles.*, published_articles.issue_id FROM articles LEFT JOIN " .
            "published_articles ON published_articles.article_id = articles.article_id WHERE " .
            "articles.article_id = :id";
        $articleStatement = $this->dbalConnection->prepare($articleSql);
        $articleStatement->bindValue('id', $id);
        $articleStatement->execute();

        $settingsSql = "SELECT locale, setting_name, setting_value FROM article_settings WHERE article_id = :id";
        $settingsStatement = $this->dbalConnection->prepare($settingsSql);
        $settingsStatement->bindValue('id', $id);
        $settingsStatement->execute();

        $pkpArticle = $articleStatement->fetch();
        $pkpSettings = $settingsStatement->fetchAll();
        $settings = array();

        foreach ($pkpSettings as $setting) {
            $locale = !empty($setting['locale']) ? $setting['locale'] : 'none';
            $name = $setting['setting_name'];
            $value = $setting['setting_value'];
            $settings[$locale][$name] = $value;
        }

        $article = new Article();
        $article->setJournal($journal);
        $article->setCurrentLocale(!empty($pkpArticle['language']) ? $pkpArticle['language'] : 'en');
        $article->setPrimaryLanguage(!empty($pkpArticle['language']) ? $pkpArticle['language'] : 'en');
        $article->setDoi(!empty($settings['none']['pub-id::doi']) ? $settings['none']['pub-id::doi'] : null);

        foreach ($settings as $fieldLocale => $fields) {
            $article->setCurrentLocale(substr($fieldLocale, 0, 2));
            $article->setTitle(!empty($fields['title']) ? $fields['title'] : '-');
            $article->setTags(!empty($fields['subject']) ? substr($fields['subject'], 0, 254) : '-');
            $article->setKeywords(!empty($fields['subject']) ? substr($fields['subject'], 0, 254) : '-');
            $article->setAbstract(!empty($fields['abstract']) ? $fields['abstract'] : '-');
        }

        switch ($pkpArticle['status']) {
            case 0: // STATUS_ARCHIVED
                $article->setStatus(ArticleStatuses::STATUS_REJECTED);
                break;
            case 1: // STATUS_QUEUED
                $article->setStatus($this->determineStatus($id));
                break;
            case 3: // STATUS_PUBLISHED
                $article->setStatus(ArticleStatuses::STATUS_PUBLISHED);
                break;
            case 4: // STATUS_DECLINED
                $article->setStatus(ArticleStatuses::STATUS_REJECTED);
                break;
            case 5: // STATUS_QUEUED_UNASSIGNED
                $article->setStatus(ArticleStatuses::STATUS_INREVIEW);
                break;
            case 6: // STATUS_QUEUED_REVIEW
                $article->setStatus(ArticleStatuses::STATUS_INREVIEW);
                break;
            case 7: // STATUS_QUEUED_EDITING
                $article->setStatus(ArticleStatuses::STATUS_UNPUBLISHED);
                break;
            case 8: // STATUS_INCOMPLETE
                $article->setStatus(ArticleStatuses::STATUS_NOT_SUBMITTED);
                break;
        }

        if (!empty($pkpArticle['issue_id']) && isset($issueIds[$pkpArticle['issue_id']])) {
            /** @var Issue $issue */
            $issue = $this->em->getReference('OjsJournalBundle:Issue', $issueIds[$pkpArticle['issue_id']]);
            $article->setIssue($issue);
        }
        
        if (!empty($pkpArticle['section_id']) && isset($sectionIds[$pkpArticle['section_id']])) {
            /** @var Section $section */
            $section = $this->em->getReference('OjsJournalBundle:Section', $sectionIds[$pkpArticle['section_id']]);
            $article->setSection($section);
        }

        $article->setSubmissionDate(
            !empty($pkpArticle['date_submitted']) ?
                DateTime::createFromFormat('Y-m-d H:i:s', $pkpArticle['date_submitted']) :
                new DateTime()
        );

        $article->setPubdate(
            !empty($pkpArticle['date_submitted']) ?
                DateTime::createFromFormat('Y-m-d H:i:s', $pkpArticle['date_submitted']) :
                new DateTime()
        );

        if (isset($pkpArticle['pages'])) {
            $pages = explode('-', $pkpArticle['pages']);

            isset($pages[0]) &&
            $article->setFirstPage((int) $pages[0] == 0 && !empty($pages[0]) ?
                (int) StringHelper::roman2int($pages[0]) :
                (int) $pages[0]);

            isset($pages[1]) &&
            $article->setLastPage((int) $pages[1] == 0 && !empty($pages[1]) ?
                (int) StringHelper::roman2int($pages[1]) :
                (int) $pages[1]);
        }

        $this->em->persist($article);

        $this->importCitations($id, $article);
        $this->importAuthors($id, $article);

        $articleFileImporter = new ArticleFileImporter($this->dbalConnection, $this->em, $this->logger, $this->consoleOutput);
        $articleFileImporter->importArticleFiles($article, $id, $journal->getSlug());

        $pendingStatImport = new PendingStatisticImport($article, $id);
        $pendingSubmitterImport = new PendingSubmitterImport($article, $pkpArticle['user_id']);
        $this->em->persist($pendingStatImport);
        $this->em->persist($pendingSubmitterImport);
    }

    /**
     * Imports citations of the given article.
     * @param int $oldArticleId Old article's ID
     * @param Article $article  Newly imported Article's entity
     */
    public function importCitations($oldArticleId, $article)
    {
        $this->consoleOutput->writeln("Reading citations...");

        $citationSql = "SELECT * FROM citations WHERE assoc_id = :id";
        $citationStatement = $this->dbalConnection->prepare($citationSql);
        $citationStatement->bindValue('id', $oldArticleId);
        $citationStatement->execute();

        $orderCounter = 0;
        $citations = $citationStatement->fetchAll();
        foreach ($citations as $pkpCitation) {
            $citation = new Citation();
            $citation->setRaw(!empty($pkpCitation['raw_citation']) ? $pkpCitation['raw_citation'] : '-');
            $citation->setOrderNum(!empty($pkpCitation['seq']) ? $pkpCitation['seq'] : $orderCounter);
            $article->addCitation($citation);

            $orderCounter++;
        }
    }

    /**
     * Imports authors of the given article.
     * @param int $oldArticleId Old article's ID
     * @param Article $article  Newly imported Article entity
     */
    public function importAuthors($oldArticleId, $article)
    {
        $this->consoleOutput->writeln("Reading authors...");

        $authorSql = "SELECT first_name, last_name, email, seq FROM authors " .
            "WHERE submission_id = :id ORDER BY first_name, last_name, email";
        $authorStatement = $this->dbalConnection->prepare($authorSql);
        $authorStatement->bindValue('id', $oldArticleId);
        $authorStatement->execute();

        $authors = $authorStatement->fetchAll();
        foreach ($authors as $pkpAuthor) {
            $author = new Author();
            $author->setCurrentLocale('en');
            $author->setFirstName(!empty($pkpAuthor['first_name']) ? $pkpAuthor['first_name'] : '-');
            $author->setLastName(!empty($pkpAuthor['last_name']) ? $pkpAuthor['last_name'] : '-');
            $author->setEmail(
                !empty($pkpAuthor['email']) && $pkpAuthor['email'] !== '-' ?
                    $pkpAuthor['email'] :
                    'author@example.com'
            );

            $articleAuthor = new ArticleAuthor();
            $articleAuthor->setAuthor($author);
            $articleAuthor->setArticle($article);
            $articleAuthor->setAuthorOrder(!empty($pkpAuthor['seq']) ? $pkpAuthor['seq'] : 0);
        }
    }

    public function determineStatus($oldArticleId)
    {
        $statusSql = "SELECT decision FROM edit_decisions WHERE article_id = :id ORDER BY round DESC";
        $statusStatement = $this->dbalConnection->prepare($statusSql);
        $statusStatement->bindValue('id', $oldArticleId);
        $statusStatement->execute();

        $result = $statusStatement->fetch();

        if (!$result || empty($result['decision'])) {
            return ArticleStatuses::STATUS_INREVIEW;
        } elseif ($result['decision'] == 1) {
            return ArticleStatuses::STATUS_UNPUBLISHED;
        } elseif ($result['decision'] == 4) {
            return ArticleStatuses::STATUS_REJECTED;
        } else {
            return ArticleStatuses::STATUS_INREVIEW;
        }
    }
}
