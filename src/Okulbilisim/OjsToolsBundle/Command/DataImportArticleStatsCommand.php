<?php

namespace Okulbilisim\OjsToolsBundle\Command;

use Doctrine\DBAL\Connection;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Mapping\Annotations\Date;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManager;
use Ojs\ReportBundle\Document\ObjectDownload;
use Ojs\ReportBundle\Document\ObjectDownloads;
use Ojs\ReportBundle\Document\ObjectView;
use Ojs\ReportBundle\Document\ObjectViews;
use Ojs\JournalBundle\Document\TransferredRecord;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;

/**
 * Class DataImportArticleStatsCommand.
 */
class DataImportArticleStatsCommand extends ContainerAwareCommand
{
    /** @var  Connection */
    protected $connection;

    /** @var  EntityManager */
    protected $em;

    /** @var  DocumentManager */
    protected $dm;
    /** @var  OutputInterface */
    protected $output;

    /** @var array */
    protected $database = ['driver' => 'pdo_mysql',];

    /**
     * Configure Command.
     */
    protected function configure()
    {
        gc_collect_cycles();
        $this
            ->setName('ojs:import:article_stats')
            ->setDescription('Import article statistics')
            ->addArgument(
                'database', InputArgument::REQUIRED, 'PKP DBStats Database Connection string [root:123456@localhost/dbname]'
            );
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->parseConnectionString($input->getArgument('database'));

        $connectionFactory = $this->getContainer()->get('doctrine.dbal.connection_factory');
        $this->connection = $connectionFactory->createConnection($this->database);
        unset($connectionFactory);

        $this->em = $this->getContainer()->get('doctrine.orm.entity_manager');
        $this->em->getConnection()->getConfiguration()->getSQLLogger(null);

        $this->dm = $this->getContainer()->get('doctrine.odm.mongodb.document_manager');

        $kernel = $this->getContainer()->get('kernel');
        $application = new Application($kernel);
        $application->setAutoExit(false);
        $this->output = $output;
        $allArticles = $this->getArticleIds();
        $this->transferStatistics($allArticles);
    }


    /**
     * Get all articles ids
     * @return array
     */
    public function getArticleIds()
    {
        $query = $this->em->createQuery("SELECT a.id FROM Ojs\JournalBundle\Entity\Article a ");
        $data = $query->getResult(AbstractQuery::HYDRATE_ARRAY);
        $count = count($data);
        $this->output->writeln("<info>Total $count articles.</info>");

        return $data;
    }

    /**
     * @param $articles
     */
    public function transferStatistics($articles)
    {
        foreach ($articles as $article) {
            $oldId = $this->getRecordChange('Ojs\\JournalBundle\\Entity\\Article', $article['id'], false);
            if ($oldId) {
                $totalDownload = $this->connection->fetchAssoc("select total from article_total_download_stats where article_id={$oldId->getOldId()}");
                $totalView = $this->connection->fetchAssoc("select total from article_total_view_stats where article_id={$oldId->getOldId()}");
                $singleViews = $this->connection->fetchAll("select * from article_view_stats where article_id={$oldId->getOldId()}");
                $singleDownloads = $this->connection->fetchAll("select * from article_download_stats where article_id={$oldId->getOldId()}");
                $adoCheck = $this->dm->getRepository("OjsReportBundle:ObjectDownload")->findOneBy(['objectId' => $article['id'], 'entity' => 'article']);
                if (!$adoCheck && $totalDownload['total']) {
                    $ados = new ObjectDownload();
                    $ados->setEntity('article')
                        ->setObjectId($article['id'])
                        ->setTotal($totalDownload['total']);
                    $this->dm->persist($ados);
                    $this->output->writeln("<info> id: {$article['id']} , download: {$totalDownload['total']}  </info>");

                    foreach ($singleDownloads as $download) {
                        $ado = new ObjectDownloads();
                        $ado->setEntity('article')
                            ->setObjectId($article['id'])
                            ->setLogDate(date_create_from_format('Y-m-d H:i:s', $download['download_time'])->getTimestamp());
                        $this->dm->persist($ado);
                    }
                }

                $avoCheck = $this->dm->getRepository("OjsReportBundle:ObjectView")->findOneBy(["objectId" => $article['id'], 'entity' => 'article']);
                if (!$avoCheck && $totalView['total']) {
                    $avos = new ObjectView();
                    $avos->setTotal($totalView['total'])
                        ->setEntity('article')
                        ->setObjectId($article['id']);
                    $this->dm->persist($avos);
                    $this->output->writeln("<info> id: {$article['id']} , view: {$totalView['total']} </info>");

                    foreach ($singleViews as $view) {
                        $avo = new ObjectViews();
                        $avo->setEntity('article')
                            ->setObjectId($article['id'])
                            ->setLogDate(date_create_from_format('Y-m-d H:i:s', $view['view_time'])->getTimestamp());
                        $this->dm->persist($avo);
                    }

                }

                $this->dm->flush();
            }
        }

    }


    /**
     * @param $entity
     * @param $id
     * @param bool $isNew
     * @return TransferredRecord
     */
    protected function getRecordChange($entity, $id, $isNew = false)
    {
        $repo = $this->dm->getRepository('OjsJournalBundle:TransferredRecord');
        if (!$isNew) {
            $id = ['new_id' => $id];
        } else {
            $id = ['old_id' => $id];
        }
        /** @var TransferredRecord $result */
        $result = $repo->findOneBy(array_merge(['entity' => $entity], $id));
        return $result;
    }

    /**
     * Parse database connection string
     * @param $connectionString
     * @throws \Exception
     */
    private function parseConnectionString($connectionString)
    {
        preg_match_all("~([^\:]+)\:([^\@]+)?\@([^\/]+)\/(.*)~", $connectionString, $matches);

        if (isset($matches[1])) {
            $this->database['user'] = $matches[1][0];
        } else {
            throw new \Exception('Hatalı parametre.');
        }
        if (isset($matches[2])) {
            $this->database['password'] = empty($matches[2][0]) ? null : $matches[2][0];
        } else {
            throw new \Exception('Hatalı parametre.');
        }
        if (isset($matches[3])) {
            $this->database['host'] = $matches[3][0];
        } else {
            throw new \Exception('Hatalı parametre.');
        }
        if (isset($matches[4])) {
            $this->database['dbname'] = $matches[4][0];
        } else {
            throw new \Exception('Hatalı parametre.');
        }

        $this->database['charset'] = 'utf8';
    }
}
