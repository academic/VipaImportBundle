<?php

namespace Ojs\ImportBundle\Importer\PKP;

use Ojs\ImportBundle\Entity\PendingSubmitterImport;
use Ojs\JournalBundle\Entity\Article;
use Ojs\ImportBundle\Importer\Importer;

class ArticleSubmitterImporter extends Importer
{
    /**
     * @param UserImporter $userImporter
     */
    public function importArticleSubmitter($userImporter)
    {
        $this->consoleOutput->writeln("Importing article submitters...");

        $result = $this->em
            ->getRepository(PendingSubmitterImport::class)
            ->createQueryBuilder('import')
            ->select('import.id')
            ->getQuery()->getScalarResult();
        $ids = array_column($result, 'id');
        $counter = 0;

        foreach ($ids as $id) {
            $import = $this->em->find(PendingSubmitterImport::class, $id);

            if (!$import || !$import->getArticle()) {
                continue;
            }

            $user = $userImporter->importUser($import->getOldId());

            if ($user) {
                /** @var Article $article */
                $article = $import->getArticle();
                $article->setSubmitterUser($user);
                $this->em->persist($article);
            }

            $this->em->remove($import);
            $counter++;

            if ($counter % 10 == 0) {
                $this->em->flush();
                $this->em->clear();
            }
        }

        $this->em->flush();
    }
}
