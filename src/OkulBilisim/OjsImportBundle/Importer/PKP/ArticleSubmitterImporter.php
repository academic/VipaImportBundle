<?php

namespace OkulBilisim\OjsImportBundle\Importer\PKP;

use Ojs\JournalBundle\Entity\Article;
use OkulBilisim\OjsImportBundle\Importer\Importer;

class ArticleSubmitterImporter extends Importer
{
    /**
     * @param UserImporter $userImporter
     */
    public function importArticleSubmitter($userImporter)
    {
        $pendingImports = $this->em->getRepository('OkulBilisimOjsImportBundle:PendingSubmitterImport')->findAll();
        $this->consoleOutput->writeln("Importing article submitters...");

        foreach ($pendingImports as $import) {
            $user = $userImporter->importUser($import->getOldId());

            if ($user) {
                /** @var Article $article */
                $article = $import->getArticle();
                $article->setSubmitterUser($user);

                $this->em->persist($article);
            }

            $this->em->remove($import);
            $this->em->flush();
        }
    }
}
