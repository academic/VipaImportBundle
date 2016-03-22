<?php

namespace Ojs\ImportBundle\Importer\PKP;

use DateTime;
use Ojs\AnalyticsBundle\Entity\ArticleFileStatistic;
use Ojs\AnalyticsBundle\Entity\ArticleStatistic;
use Ojs\ImportBundle\Importer\Importer;

class ArticleStatisticImporter extends Importer
{
    /**
     * Imports article statistics whose import are pending.
     */
    public function importArticleStatistics()
    {
        $pendingImports = $this->em->getRepository('ImportBundle:PendingStatisticImport')->findAll();
        $this->consoleOutput->writeln("Importing article statistics...");

        foreach ($pendingImports as $import) {
            $this->importArticleStatistic($import->getOldId(), $import->getArticle()->getId());
            $this->em->remove($import);
            $this->em->flush($import);
        }
    }

    /**
     * Imports the given article's statistics
     * @param int $oldId Old ID of the article
     * @param int $newId New ID of the article
     * @throws \Doctrine\DBAL\DBALException
     */
    public function importArticleStatistic($oldId, $newId)
    {
        $article = $this->em->getRepository('OjsJournalBundle:Article')->find($newId);

        if (!$article) {
            $this->consoleOutput->writeln("Couldn't find #" . $newId . " on the new database.");
            return;
        }

        $this->consoleOutput->writeln("Reading view statistics for #" . $oldId . "...");
        $viewStatsSql = "SELECT DATE(view_time) AS date, COUNT(*) as view FROM " .
            "article_view_stats WHERE article_id = :id GROUP BY DATE(view_time)";
        $viewStatsStatement = $this->dbalConnection->prepare($viewStatsSql);
        $viewStatsStatement->bindValue('id', $oldId);
        $viewStatsStatement->execute();

        $this->consoleOutput->writeln("Reading download statistics for #" . $oldId . "...");
        $downloadStatsSql = "SELECT DATE(download_time) AS date, COUNT(*) as download FROM " .
            "article_download_stats WHERE article_id = :id GROUP BY DATE(download_time)";
        $downloadStatsStatement = $this->dbalConnection->prepare($downloadStatsSql);
        $downloadStatsStatement->bindValue('id', $oldId);
        $downloadStatsStatement->execute();
        
        $pkpViewStats = $viewStatsStatement->fetchAll();
        $pkpDownloadStats = $downloadStatsStatement->fetchAll();
        foreach ($pkpViewStats as $stat) {
            $articleFileStatistic = new ArticleStatistic();
            $articleFileStatistic->setArticle($article);
            $articleFileStatistic->setDate(DateTime::createFromFormat('Y-m-d', $stat['date']));
            $articleFileStatistic->setView($stat['view']);
            $this->em->persist($articleFileStatistic);
        }

        if (!$article->getArticleFiles()->isEmpty()) {
            foreach ($pkpDownloadStats as $stat) {
                $articleFileStatistic = new ArticleFileStatistic();
                $articleFileStatistic->setArticleFile($article->getArticleFiles()->first());
                $articleFileStatistic->setDate(DateTime::createFromFormat('Y-m-d', $stat['date']));
                $articleFileStatistic->setDownload($stat['download']);
                $this->em->persist($articleFileStatistic);
            }
        }

        $this->em->flush();
    }
}
