<?php

namespace Ojs\ImportBundle\Entity;

use Doctrine\ORM\EntityRepository;
use Ojs\JournalBundle\Entity\Article;
use Ojs\JournalBundle\Entity\Issue;
use Ojs\JournalBundle\Entity\Section;

class ImportMapRepository extends EntityRepository
{
    /**
     * @param $journal
     * @return array|null
     */
    public function getArticleIds($journal)
    {
        $importedArticleIds = null;
        $importedArticles = array_values(
            $this
                ->createQueryBuilder('map')
                ->select('map.oldId, map.newId')
                ->join(Article::class, 'article', 'WITH', 'map.newId = article.id')
                ->where('map.type = :type')
                ->andWhere('article.journal = :journal')
                ->setParameter('type', Article::class)
                ->setParameter('journal', $journal)
                ->getQuery()->getScalarResult()
        );

        foreach ($importedArticles as $key => $importedArticle) {
            $importedArticleIds[$importedArticle['oldId']] = $importedArticle['newId'];
        }

        return $importedArticleIds;
    }
    /**
     * @param $journal
     * @return array|null
     */
    public function getIssueIds($journal)
    {
        $importedIssueIds = null;
        $importedIssues = array_values(
            $this
                ->createQueryBuilder('map')
                ->select('map.oldId, map.newId')
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

    /**
     * @param $journal
     * @return array|null
     */
    public function getSectionIds($journal)
    {
        $importedSectionIds = null;
        $importedSections = array_values(
            $this
                ->createQueryBuilder('map')
                ->select('map.oldId, map.newId')
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
}
