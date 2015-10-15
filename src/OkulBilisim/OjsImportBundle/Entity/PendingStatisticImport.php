<?php

namespace OkulBilisim\OjsImportBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Ojs\JournalBundle\Entity\Article;

/**
 * PendingStatisticImport
 *
 * @ORM\Table()
 * @ORM\Entity(repositoryClass="OkulBilisim\OjsImportBundle\Entity\PendingStatisticImportRepository")
 * @ORM\Table("import_pending_statistic_import")
 */
class PendingStatisticImport
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     * @var int
     */
    private $id;

    /**
     * @var Article
     * @ORM\OneToOne(targetEntity="Ojs\JournalBundle\Entity\Article")
     * @ORM\JoinColumn(name="article_id", referencedColumnName="id")
     **/
    private $article;

    /**
     * @var integer
     * @ORM\Column(name="old_id", type="bigint")
     */
    private $oldId;

    /**
     * PendingStatisticImport constructor.
     * @param Article $article
     * @param int $oldId
     */
    public function __construct(Article $article, $oldId)
    {
        $this->article = $article;
        $this->oldId = $oldId;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getArticle()
    {
        return $this->article;
    }

    /**
     * @param mixed $article
     */
    public function setArticle($article)
    {
        $this->article = $article;
    }

    /**
     * Set oldId
     * @param integer $oldId
     * @return PendingStatisticImport
     */
    public function setOldId($oldId)
    {
        $this->oldId = $oldId;

        return $this;
    }

    /**
     * Get oldId
     * @return integer
     */
    public function getOldId()
    {
        return $this->oldId;
    }
}

