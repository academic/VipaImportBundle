<?php

namespace Ojs\ImportBundle\Importer\PKP;

use Jb\Bundle\FileUploaderBundle\Entity\FileHistory;
use Ojs\JournalBundle\Entity\Issue;
use Ojs\JournalBundle\Entity\IssueFile;
use Ojs\ImportBundle\Entity\PendingDownload;
use Ojs\ImportBundle\Helper\FileHelper;
use Ojs\ImportBundle\Importer\Importer;

class IssueFileImporter extends Importer
{
    /**
     * Imports files of given issue
     * @param Issue  $issue The issue whose files are going to be imported
     * @param int    $oldId Old ID of the issue
     * @param String $slug Journal's slug
     * @throws \Doctrine\DBAL\DBALException
     */
    public function importIssueFiles($issue, $oldId, $slug)
    {
        $issueFilesSql = "SELECT * FROM issue_files WHERE issue_id = :id";
        $issueFilesStatement = $this->dbalConnection->prepare($issueFilesSql);
        $issueFilesStatement->bindValue('id', $oldId);
        $issueFilesStatement->execute();

        $issueFiles = $issueFilesStatement->fetchAll();
        foreach ($issueFiles as $issueFile) {
            $this->importIssueFile($issueFile['file_id'], $oldId, $issue, $slug);
        }
    }

    /**
     * Imports the given issue file
     * @param int    $id    Issue file's ID
     * @param int    $oldId Old issue file's ID
     * @param Issue  $issue File's issue
     * @param string $slug  Journal's slug
     */
    public function importIssueFile($id, $oldId, $issue, $slug)
    {
        $this->consoleOutput->writeln("Reading issue file #" . $id . "... ", true);

        $issueFileSql = "SELECT * FROM issue_files WHERE file_id = :id LIMIT 1";
        $issueFileStatement = $this->dbalConnection->prepare($issueFileSql);
        $issueFileStatement->bindValue('id', $id);
        $issueFileStatement->execute();

        $galleysSql = "SELECT galley_id, issue_id, locale, label FROM issue_galleys " .
            "WHERE issue_id = :issue_id AND file_id = :id";
        $galleysStatement = $this->dbalConnection->prepare($galleysSql);
        $galleysStatement->bindValue('issue_id', $oldId);
        $galleysStatement->bindValue('id', $id);
        $galleysStatement->execute();

        $pkpIssueFile = $issueFileStatement->fetch();
        $pkpGalleys = $galleysStatement->fetchAll();

        foreach ($pkpGalleys as $galley) {
            $locale = !empty($galley['locale']) ? substr($galley['locale'], 0, 2) : 'en';
            $label = !empty($galley['label']) ? $galley['label'] : '-';
            $filename = sprintf('imported/%s/%s.%s',
                $galley['issue_id'],
                $galley['galley_id'],
                FileHelper::$mimeToExtMap[$pkpIssueFile['file_type']]);

            $issueFile = new IssueFile();
            $issueFile->setFile($filename);
            $issueFile->setIssue($issue);
            $issueFile->setVersion(0);
            $issueFile->setType(0);

            // Fill translatable fields
            $issueFile->setCurrentLocale($locale);
            $issueFile->setTitle($label);
            $issueFile->setDescription('-');

            $history = new FileHistory();
            $history->setFileName($filename);
            $history->setOriginalName($pkpIssueFile['original_file_name']);
            $history->setType('issuefiles');

            $source = sprintf('%s/issue/download/%s/%s', $slug, $galley['issue_id'], $galley['galley_id']);
            $target = sprintf('/../web/uploads/issuefiles/imported/%s/%s.%s',
                $galley['issue_id'],
                $galley['galley_id'],
                FileHelper::$mimeToExtMap[$pkpIssueFile['file_type']]);

            $pendingDownload = new PendingDownload();
            $pendingDownload->setSource($source);
            $pendingDownload->setTarget($target);

            $this->em->persist($pendingDownload);
            $this->em->persist($issueFile);
            $this->em->persist($history);
        }
    }
}
