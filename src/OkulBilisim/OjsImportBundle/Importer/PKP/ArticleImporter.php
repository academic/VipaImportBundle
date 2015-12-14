<?php

namespace OkulBilisim\OjsImportBundle\Importer\PKP;

use DateTime;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Ojs\JournalBundle\Entity\Article;
use Ojs\JournalBundle\Entity\ArticleAuthor;
use Ojs\JournalBundle\Entity\Author;
use Ojs\JournalBundle\Entity\Citation;
use Ojs\JournalBundle\Entity\Journal;
use OkulBilisim\OjsImportBundle\Entity\PendingStatisticImport;
use OkulBilisim\OjsImportBundle\Entity\PendingSubmitterImport;
use OkulBilisim\OjsImportBundle\Helper\StringHelper;
use OkulBilisim\OjsImportBundle\Importer\Importer;
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
     * @param int $oldJournalId
     * @param Journal $journal
     * @param array $issues
     * @param array $sections
     */
    public function importArticles($oldJournalId, $journal, $issues, $sections)
    {
        $articleSql = "SELECT article_id FROM articles WHERE journal_id = :journal_id";
        $articleStatement = $this->dbalConnection->prepare($articleSql);
        $articleStatement->bindValue('journal_id', $oldJournalId);
        $articleStatement->execute();
        $articles = $articleStatement->fetchAll();

        foreach ($articles as $article) {
            $this->importArticle($article['article_id'], $journal, $issues, $sections);
        }
    }

    /**
     * @param int $id
     * @param Journal $journal
     * @param array $issues
     * @param array $sections
     */
    private function importArticle($id, $journal, $issues, $sections)
    {
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

        $viewStatsSql = "SELECT total FROM article_total_view_stats WHERE article_id = :id";
        $viewStatsStatement = $this->dbalConnection->prepare($viewStatsSql);
        $viewStatsStatement->bindValue('id', $id);
        $viewStatsStatement->execute();

        $downloadStatsSql = "SELECT total FROM article_total_download_stats WHERE article_id = :id";
        $downloadStatsStatement = $this->dbalConnection->prepare($downloadStatsSql);
        $downloadStatsStatement->bindValue('id', $id);
        $downloadStatsStatement->execute();

        $pkpArticle = $articleStatement->fetch();
        $pkpSettings = $settingsStatement->fetchAll();
        $pkpViewStats = $viewStatsStatement->fetch();
        $pkpDownloadStats = $downloadStatsStatement->fetch();
        $settings = array();

        foreach ($pkpSettings as $setting) {
            $locale = !empty($setting['locale']) ? $setting['locale'] : 'en_US';
            $name = $setting['setting_name'];
            $value = $setting['setting_value'];
            $settings[$locale][$name] = $value;
        }

        $article = new Article();
        $article->setJournal($journal);
        $article->setCurrentLocale(!empty($pkpArticle['language']) ? $pkpArticle['language'] : 'en');
        $article->setPrimaryLanguage(!empty($pkpArticle['language']) ? $pkpArticle['language'] : 'en');

        foreach ($settings as $fieldLocale => $fields) {
            $article->setCurrentLocale(substr($fieldLocale, 0, 2));
            $article->setTitle(!empty($fields['title']) ? $fields['title'] : '-');
            $article->setSubjects(!empty($fields['subject']) ? substr($fields['subject'], 0, 254) : '-');
            $article->setKeywords(!empty($fields['subject']) ? substr($fields['subject'], 0, 254) : '-');
            $article->setAbstract(!empty($fields['abstract']) ? $fields['abstract'] : '-');
        }

        switch ($pkpArticle['status']) {
            case 0:
                $article->setStatus(0);  // In Review
                break;
            case 1:
                $article->setStatus(-2); // Unpublished
                break;
            case 3:
                $article->setStatus(1);  // Published
                break;
            case 4:
                $article->setStatus(-3); // Rejected
                break;
        }

        $article->setIssue(
            !empty($pkpArticle['issue_id']) && isset($issues[$pkpArticle['issue_id']]) ?
                $issues[$pkpArticle['issue_id']] : null
        );

        $article->setSection(
            !empty($pkpArticle['section_id']) && isset($sections[$pkpArticle['section_id']]) ?
                $sections[$pkpArticle['section_id']] : null
        );

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

        !empty($pkpViewStats['total']) ?
            $article->setViewCount($pkpViewStats['total']) :
            $article->setViewCount(0);
        !empty($pkpDownloadStats['total']) ?
            $article->setDownloadCount($pkpDownloadStats['total']) :
            $article->setDownloadCount(0);

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
     * @param int $oldArticleId
     * @param Article $article
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
     * @param int $oldArticleId
     * @param Article $article
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
            $author->setTitle('-');
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
}