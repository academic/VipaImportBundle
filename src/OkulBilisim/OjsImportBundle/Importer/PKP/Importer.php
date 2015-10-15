<?php

namespace OkulBilisim\OjsImportBundle\Importer\PKP;

use InvalidArgumentException;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Console\Output\OutputInterface;

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
     * @var OutputInterface
     */
    protected $consoleOutput;

    /**
     * Importer constructor.
     * @param Connection $connection
     * @param EntityManager $em
     * @param OutputInterface $consoleOutput
     */
    public function __construct(Connection $connection, EntityManager $em, OutputInterface $consoleOutput)
    {
        $this->connection = $connection;
        $this->em = $em;
        $this->consoleOutput = $consoleOutput;
    }

    protected function throwInvalidArgumentException($message)
    {
        throw new InvalidArgumentException($message);
    }
}