<?php

namespace Ojs\ImportBundle\Importer;

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
     * @param Connection $dbalConnection
     * @param EntityManager $em
     * @param LoggerInterface $logger
     * @param OutputInterface $consoleOutput
     */
    public function __construct(
        Connection $dbalConnection, EntityManager $em, LoggerInterface $logger, OutputInterface $consoleOutput
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
