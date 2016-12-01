<?php

namespace Ojs\ImportBundle\Importer\PKP;

use Jb\Bundle\FileUploaderBundle\Entity\FileHistory;
use Ojs\CoreBundle\Params\ArticleFileParams;
use Ojs\ImportBundle\Entity\PendingDownload;
use Ojs\ImportBundle\Helper\FileHelper;
use Ojs\ImportBundle\Importer\Importer;
use Ojs\JournalBundle\Entity\Article;
use Ojs\JournalBundle\Entity\ArticleFile;
use Symfony\Component\PropertyAccess\PropertyAccess;

class SupFileImporter extends Importer
{
    /**
     * @param Article $article New article entity
     * @param integer $oldArticleId Old ID of the article
     * @param string $oldJournalSlug Slug of the article's journal
     */
    public function importSupFiles(Article $article, $oldArticleId, $oldJournalSlug)
    {
        $builder = $this->dbalConnection->createQueryBuilder();
        $builder
            ->from("article_supplementary_files", "supp")
            ->join("supp", "article_files", "file", "supp.file_id = file.file_id")
            ->select("file.file_id, file.file_type, file.original_file_name, file.revision, supp.supp_id")
            ->where("supp.article_id = :id")
            ->setParameter("id", $oldArticleId);
        $result = $builder->execute()->fetchAll();

        foreach ($result as $row) {
            $this->importSupFile($article, $row, $oldArticleId, $oldJournalSlug);
        }
    }

    /**
     * @param Article $article New article entity
     * @param array $row Supp file row from the source database
     * @param integer $oldArticleId Old ID of the article
     * @param string $oldJournalSlug Slug of the article's journal
     */
    public function importSupFile(Article $article, $row, $oldArticleId, $oldJournalSlug)
    {
        $settings = $this->getSettings($row["supp_id"]);

        if (empty($settings)) {
            return;
        }

        $accessor = PropertyAccess::createPropertyAccessor();
        $code = $article->getJournal()->getMandatoryLang()->getCode();
        $settings = empty($settings[$code]) ? current($settings) : $settings[$code];

        $fileFormat = "imported/supplementary/%s/%s.%s";
        $extension = FileHelper::$mimeToExtMap[$row["file_type"]];
        $filename = sprintf($fileFormat, $oldArticleId, $row["file_id"], $extension);
        $keywords = mb_substr($accessor->getValue($settings, "[subject]"), 0, 255);

        $file = new ArticleFile();
        $file->setVersion(0);
        $file->setLangCode($code);
        $file->setFile($filename);
        $file->setArticle($article);
        $file->setKeywords($keywords);
        $file->setType(ArticleFileParams::SUPPLEMENTARY_FILE);
        $file->setTitle($accessor->getValue($settings, "[title]"));
        $file->setDescription($accessor->getValue($settings, "[description]"));

        $history = $this->em->getRepository(FileHistory::class)->findOneBy(["fileName" => $filename]);

        if (!$history) {
            $history = new FileHistory();
            $history->setFileName($filename);
            $history->setType("articlefiles");
            $history->setOriginalName($row["original_file_name"]);
            $this->em->persist($history);
        }

        $source = sprintf("%s/article/downloadSuppFile/%s/%s", $oldJournalSlug, $oldArticleId, $row["supp_id"]);
        $target = sprintf("/../web/uploads/articlefiles/%s", $filename);

        $download = new PendingDownload();
        $download->setSource($source);
        $download->setTarget($target);

        $this->em->persist($file);
        $this->em->persist($download);
    }

    private function getSettings($id)
    {
        $builder = $this->dbalConnection->createQueryBuilder();

        $predicates = $builder->expr()->orX(
            "setting_name = 'description'",
            "setting_name = 'subject'",
            "setting_name = 'title'"
        );

        $builder
            ->select("locale, setting_name, setting_value")
            ->from("article_supp_file_settings")
            ->where("supp_id = :id")
            ->andWhere($predicates)
            ->setParameter("id", $id);

        $rows = $builder->execute()->fetchAll();
        $settings = [];

        foreach ($rows as $row) {
            $locale = mb_substr($row["locale"], 0, 2);
            $value = mb_substr($row["setting_value"], 0, 255);
            $settings[$locale][$row["setting_name"]] = $value;
        }

        return $settings;
    }
}
