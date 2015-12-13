<?php

namespace OkulBilisim\OjsImportBundle\Importer;

use Doctrine\DBAL\Connection as DBALConnection;
use Doctrine\ORM\EntityManager;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Importer
{
    /**
     * @var DBALConnection
     */
    protected $dbalConnection;

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
     * @param DBALConnection $dbalConnection
     * @param EntityManager $em
     * @param LoggerInterface $logger
     * @param OutputInterface $consoleOutput
     */
    public function __construct(
        DBALConnection $dbalConnection, EntityManager $em, LoggerInterface $logger, OutputInterface $consoleOutput
    )
    {
        $this->dbalConnection = $dbalConnection;
        $this->em = $em;
        $this->logger = $logger;
        $this->consoleOutput = $consoleOutput;
    }

    protected function throwInvalidArgumentException($message)
    {
        throw new InvalidArgumentException($message);
    }
}