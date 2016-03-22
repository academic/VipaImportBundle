<?php

namespace Ojs\ImportBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Class PendingDownload
 * @package Ojs\ImportBundle\Entity
 * @ORM\Entity
 * @ORM\Table("import_pending_download")
 */
class PendingDownload
{
    /**
     * @ORM\Id
     * @ORM\Column(type="integer")
     * @ORM\GeneratedValue(strategy="AUTO")
     * @var int
     */
    private $id;

    /**
     * @ORM\Column(type="text")
     * @var string
     */
    private $source;

    /**
     * @ORM\Column(type="text")
     * @var string
     */
    private $target;

    /**
     * @ORM\Column(type="string", nullable=true)
     * @var string
     */
    private $tag;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return string
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * @param string $source
     * @return PendingDownload
     */
    public function setSource($source)
    {
        $this->source = $source;

        return $this;
    }

    /**
     * @return string
     */
    public function getTarget()
    {
        return $this->target;
    }

    /**
     * @param string $target
     * @return PendingDownload
     */
    public function setTarget($target)
    {
        $this->target = $target;

        return $this;
    }

    /**
     * @return string
     */
    public function getTag()
    {
        return $this->tag;
    }

    /**
     * @param string $tag
     * @return PendingDownload
     */
    public function setTag($tag)
    {
        $this->tag = $tag;

        return $this;
    }
}
