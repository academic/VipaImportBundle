<?php

namespace OkulBilisim\OjsImportBundle\Importer\PKP;

use InvalidArgumentException;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;

class Importer
{
    /**
     * @var Connection
     */
    protected $connection;

    /**
     * @var EntityManager
     */
    protected $em;

    /**
     * Importer constructor.
     * @param Connection $connection
     * @param EntityManager $em
     */
    public function __construct(Connection $connection, EntityManager $em)
    {
        $this->connection = $connection;
        $this->em = $em;
    }

    protected function throwInvalidArgumentException($message)
    {
        throw new InvalidArgumentException($message);
    }
}