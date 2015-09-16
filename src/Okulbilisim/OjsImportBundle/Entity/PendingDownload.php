<?php

namespace Okulbilisim\OjsImportBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Class PendingDownload
 * @package Okulbilisim\OjsImportBundle\Entity
 * @ORM\Entity
 * @ORM\Table("pending_download")
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
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
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
     */
    public function setSource($source)
    {
        $this->source = $source;
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
     */
    public function setTarget($target)
    {
        $this->target = $target;
    }
}