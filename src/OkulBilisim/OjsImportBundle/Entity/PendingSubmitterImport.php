<?php

namespace OkulBilisim\OjsImportBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Ojs\JournalBundle\Entity\Article;

/**
 * PendingSubmitterImport
 *
 * @ORM\Table()
 * @ORM\Entity(repositoryClass="OkulBilisim\OjsImportBundle\Entity\PendingSubmitterImportRepository")
 * @ORM\Table("import_pending_submitter_import")
 */
class PendingSubmitterImport
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
     * PendingSubmitterImport constructor.
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
     * @return PendingSubmitterImport
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

