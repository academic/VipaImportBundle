<?php

namespace OkulBilisim\OjsImportBundle\Importer\PKP;

use Jb\Bundle\FileUploaderBundle\Entity\FileHistory;
use Ojs\JournalBundle\Entity\Article;
use Ojs\JournalBundle\Entity\ArticleFile;
use OkulBilisim\OjsImportBundle\Entity\PendingDownload;
use OkulBilisim\OjsImportBundle\Helper\FileHelper;

class ArticleFileImporter extends Importer
{
    public function importArticleFiles($article, $oldId, $slug)
    {
        $articleFilesSql = "SELECT * FROM article_files WHERE article_id = :id";
        $articleFilesStatement = $this->connection->prepare($articleFilesSql);
        $articleFilesStatement->bindValue('id', $oldId);
        $articleFilesStatement->execute();

        $articleFiles = $articleFilesStatement->fetchAll();
        foreach ($articleFiles as $articleFile) {
            $this->importArticleFile($articleFile['file_id'], $oldId, $article, $slug);
        }
    }

    /**
     * @param int     $id
     * @param int     $oldId
     * @param Article $article
     * @param string  $slug
     */
    public function importArticleFile($id, $oldId, $article, $slug)
    {
        $articleFileSql = "SELECT * FROM article_files WHERE file_id = :id LIMIT 1";
        $articleFileStatement = $this->connection->prepare($articleFileSql);
        $articleFileStatement->bindValue('id', $id);
        $articleFileStatement->execute();

        $galleysSql = "SELECT galley_id, article_id, locale, label FROM article_galleys " .
            "WHERE article_id = :article_id AND file_id = :id";
        $galleysStatement = $this->connection->prepare($galleysSql);
        $galleysStatement->bindValue('article_id', $oldId);
        $galleysStatement->bindValue('id', $id);
        $galleysStatement->execute();

        $pkpArticleFile = $articleFileStatement->fetch();
        $pkpGalleys = $galleysStatement->fetchAll();

        foreach ($pkpGalleys as $galley) {
            $locale = !empty($galley['locale']) ? substr($galley['locale'], 0, 2) : 'en';
            $label = !empty($galley['label']) ? $galley['label'] : '-';
            $filename = sprintf('imported/%s/%s.%s',
                $galley['article_id'],
                $galley['galley_id'],
                FileHelper::$mimeToExtMap[$pkpArticleFile['file_type']]);

            $articleFile = new ArticleFile();
            $articleFile->setFile($filename);
            $articleFile->setArticle($article);
            $articleFile->setVersion(0);
            $articleFile->setTitle($label);
            $articleFile->setLangCode($locale);
            $articleFile->setDescription('-');
            $articleFile->setType(0);

            $history = new FileHistory();
            $history->setFileName($filename);
            $history->setOriginalName($pkpArticleFile['original_file_name']);
            $history->setType('articlefiles');

            $source = sprintf('%s/article/download/%s/%s', $slug, $galley['article_id'], $galley['galley_id']);
            $target = sprintf('/../web/uploads/articlefiles/imported/%s/%s.%s',
                $galley['article_id'],
                $galley['galley_id'],
                FileHelper::$mimeToExtMap[$pkpArticleFile['file_type']]);

            $pendingDownload = new PendingDownload();
            $pendingDownload->setSource($source);
            $pendingDownload->setTarget($target);

            $this->em->persist($pendingDownload);
            $this->em->persist($articleFile);
            $this->em->persist($history);
        }
    }
}