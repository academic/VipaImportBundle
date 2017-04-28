<?php

namespace Vipa\ImportBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Map
 *
 * @ORM\Entity(repositoryClass="Vipa\ImportBundle\Entity\ImportMapRepository")
 * @ORM\Table("import_map")
 */
class ImportMap
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="bigint")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var integer
     *
     * @ORM\Column(name="old_id", type="bigint")
     */
    private $oldId;

    /**
     * @var integer
     *
     * @ORM\Column(name="new_id", type="integer")
     */
    private $newId;

    /**
     * @var string
     *
     * @ORM\Column(name="type", type="string", length=255)
     */
    private $type;

    /**
     * Map constructor.
     * @param int $oldId
     * @param int $newId
     * @param string $type
     */
    public function __construct($oldId, $newId, $type)
    {
        $this->oldId = $oldId;
        $this->newId = $newId;
        $this->type = $type;
    }

    /**
     * Get id
     *
     * @return integer
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set oldId

     * @param integer $oldId

     * @return ImportMap
     */
    public function setOldId($oldId)
    {
        $this->oldId = $oldId;

        return $this;
    }

    /**
     * Get oldId
     *
     * @return integer
     */
    public function getOldId()
    {
        return $this->oldId;
    }

    /**
     * Set newId

     * @param integer $newId

     * @return ImportMap
     */
    public function setNewId($newId)
    {
        $this->newId = $newId;

        return $this;
    }

    /**
     * Get newId
     *
     * @return integer
     */
    public function getNewId()
    {
        return $this->newId;
    }

    /**
     * Set type

     * @param string $type

     * @return ImportMap
     */
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get type
     *
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     *
     */
    function __toString()
    {
        return $this->getOldId() . " => " . $this->getNewId() . " :: " . $this->getType();
    }
}

