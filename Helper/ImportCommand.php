<?php

namespace Ojs\ImportBundle\Helper;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Gedmo\Loggable\LoggableListener;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ImportCommand extends ContainerAwareCommand
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
     * @var LoggerInterface
     */
    protected $logger;

    protected function configure()
    {
        $this
            ->addArgument('host', InputArgument::REQUIRED, 'Hostname of PKP/OJS database server')
            ->addArgument('username', InputArgument::REQUIRED, 'Username for PKP/OJS database server')
            ->addArgument('password', InputArgument::REQUIRED, 'Password for PKP/OJS database server')
            ->addArgument('database', InputArgument::REQUIRED, 'Name of PKP/OJS database')
            ->addArgument('driver', InputArgument::OPTIONAL, 'Database driver', 'pdo_mysql')
            ->addOption('disable-logging', null, InputOption::VALUE_NONE, 'Disable logging of entity creations');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $parameters = [
            'host' => $input->getArgument('host'),
            'user' => $input->getArgument('username'),
            'password' => $input->getArgument('password'),
            'dbname' => $input->getArgument('database'),
            'driver' => $input->getArgument('driver'),
            'charset' => 'utf8',
        ];

        $this->connection = $this
            ->getContainer()
            ->get('doctrine.dbal.connection_factory')
            ->createConnection($parameters);

        $this->em = $this->getContainer()->get('doctrine.orm.entity_manager');

        $path = $this->getContainer()->getParameter('kernel.root_dir') . '/logs/import.log';
        $this->logger = $this->getContainer()->get('logger');
        $this->logger->pushHandler(new StreamHandler($path, Logger::INFO));

        if ($input->getOption('disable-logging')) {
            $this->disableLoggableExtension();
        }
    }

    protected function disableLoggableExtension()
    {
        $instance = null;

        foreach ($this->em->getEventManager()->getListeners() as $event => $listeners) {
            foreach ($listeners as $hash => $listener) {
                if ($listener instanceof LoggableListener) {
                    $instance = $listener;
                    break 2;
                }
            }
        }

        if ($instance) {
            $evm = $this->em->getEventManager();
            $evm->removeEventListener(['onFlush'], $instance);
            $evm->removeEventListener(['postPersist'], $instance);
            $evm->removeEventListener(['loadClassMetadata'], $instance);
        }
    }
}
