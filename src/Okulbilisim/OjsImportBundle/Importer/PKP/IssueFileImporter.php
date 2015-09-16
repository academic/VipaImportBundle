<?php

namespace Okulbilisim\OjsImportBundle\Importer\PKP;

use Jb\Bundle\FileUploaderBundle\Entity\FileHistory;
use Ojs\JournalBundle\Entity\Issue;
use Ojs\JournalBundle\Entity\IssueFile;
use Ojs\JournalBundle\Entity\IssueFileTranslation;
use Okulbilisim\OjsImportBundle\Entity\PendingDownload;
use Okulbilisim\OjsImportBundle\Helper\FileHelper;

class IssueFileImporter extends Importer
{
    private $galleys;

    public function importIssueFiles($issue, $oldId, $slug)
    {
        $issueFilesSql = "SELECT * FROM issue_files WHERE issue_id = :id";
        $issueFilesStatement = $this->connection->prepare($issueFilesSql);
        $issueFilesStatement->bindValue('id', $oldId);
        $issueFilesStatement->execute();

        $issueFiles = $issueFilesStatement->fetchAll();
        foreach ($issueFiles as $issueFile) {
            $this->importIssueFile($issueFile['file_id'], $oldId, $issue, $slug);
        }
    }

    /**
     * @param int    $id
     * @param int    $oldId
     * @param Issue  $issue
     * @param string $slug
     */
    public function importIssueFile($id, $oldId, $issue, $slug)
    {
        $issueFileSql = "SELECT * FROM issue_files WHERE file_id = :id LIMIT 1";
        $issueFileStatement = $this->connection->prepare($issueFileSql);
        $issueFileStatement->bindValue('id', $id);
        $issueFileStatement->execute();

        $galleysSql = "SELECT locale, label FROM issue_galleys WHERE issue_id = :issue_id AND file_id = :id";
        $galleysStatement = $this->connection->prepare($galleysSql);
        $galleysStatement->bindValue('issue_id', $oldId);
        $galleysStatement->bindValue('id', $id);
        $galleysStatement->execute();

        $pkpIssueFile = $issueFileStatement->fetch();
        $pkpGalleys = $galleysStatement->fetchAll();

        $issueFile = new IssueFile();
        $issueFile->setFile($pkpIssueFile['file_name']);
        $issueFile->setIssue($issue);
        $issueFile->setVersion(0);

        $history = new FileHistory();
        $history->setFileName($pkpIssueFile['file_name']);
        $history->setOriginalName($pkpIssueFile['original_file_name']);
        $history->setType('issuefiles');

        foreach ($pkpGalleys as $galley) {
            $locale = !empty($galley['locale']) ? substr($galley['locale'], 0, 2) : 'en';
            $label = !empty($galley['label']) ? $galley['label'] : '-';

            $translation = new IssueFileTranslation();
            $translation->setLocale($locale);
            $translation->setTitle($label);
            $translation->setDescription('-');
            $issueFile->addTranslation($translation);
        }

        $source = sprintf('%s/issue/download/%s/%s', $slug, $pkpIssueFile['issue_id'], $pkpIssueFile['file_id']);
        $target = sprintf('imported/%s/%s.%s',
            $pkpIssueFile['issue_id'],
            $pkpIssueFile['file_id'],
            FileHelper::$mimeToExtMap[$pkpIssueFile['file_type']]);

        $pendingDownload = new PendingDownload();
        $pendingDownload->setSource($source);
        $pendingDownload->setTarget($target);

        $this->em->persist($issueFile);
        $this->em->persist($history);
        $this->em->persist($pendingDownload);
    }
}