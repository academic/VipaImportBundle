<?php

namespace OkulBilisim\OjsImportBundle\Importer\PKP;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
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
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Importer constructor.
     * @param Connection $connection
     * @param EntityManager $em
     * @param LoggerInterface $logger
     * @param OutputInterface $consoleOutput
     */
    public function __construct(
        Connection $connection, EntityManager $em, LoggerInterface $logger, OutputInterface $consoleOutput
    )
    {
        $this->connection = $connection;
        $this->em = $em;
        $this->logger = $logger;
        $this->consoleOutput = $consoleOutput;
    }

    protected function throwInvalidArgumentException($message)
    {
        throw new InvalidArgumentException($message);
    }
}