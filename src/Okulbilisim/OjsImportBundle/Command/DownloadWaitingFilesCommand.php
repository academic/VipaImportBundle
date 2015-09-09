<?php
/**
 * Date: 2.07.15
 * Time: 16:31
 */

namespace Okulbilisim\OjsImportBundle\Command;


use Doctrine\MongoDB\EagerCursor;
use Doctrine\ODM\MongoDB\Cursor;
use Doctrine\ODM\MongoDB\DocumentManager;
use Liip\ImagineBundle\Model\Binary;
use Ojs\JournalBundle\Document\WaitingFiles;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputInterface;

class DownloadWaitingFilesCommand extends ContainerAwareCommand
{

    /** @var  DocumentManager */
    protected $dm;
    /** @var  OutputInterface */
    protected $output;

    protected $rootDir;

    /**
     * Configure Command.
     */
    protected function configure()
    {
        gc_collect_cycles();
        $this
            ->setName('ojs:waiting_files:download')
            ->setDescription('Download waiting files');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        try {
            $this->dm = $this->getContainer()->get('doctrine.odm.mongodb.document_manager');
            $this->rootDir = dirname($this->getContainer()->get('kernel')->getRootDir());
            $kernel = $this->getContainer()->get('kernel');
            $application = new Application($kernel);
            $application->setAutoExit(false);
            $this->output = $output;
            /** @var \Doctrine\ODM\MongoDB\EagerCursor $files */
            $files = $this->getFiles();
            foreach ($files as $file) {
                $this->download($file);
            }


        } catch (\Exception $e) {
            $this->output->writeln("<error>{$e->getMessage()}</error>");
        }

    }

    public function getFiles()
    {
        $qb = $this->dm->createQueryBuilder("OjsJournalBundle:WaitingFiles")->eagerCursor(true);
        $qb->where("function() { return (typeof this.downloaded ==='undefined' || this.downloaded==false) && (typeof this.download_start_at ==='undefined' ); }");

        /** @var \Doctrine\ODM\MongoDB\EagerCursor $files */
        $files = $qb->getQuery()->execute();
        return $files;
    }

    public function download(WaitingFiles $file)
    {
        $file->setDownloadStartAt(time());
        $headers = @get_headers($file->getUrl(), 1);
        if ((isset($headers['Content-Type']) && $headers['Content-Type'] == "text/html") || !isset($headers['Content-Type']))
            return;
        $fullPath = $this->rootDir . DIRECTORY_SEPARATOR . "web" . DIRECTORY_SEPARATOR . $file->getPath();
        if (!is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0777, true);
        }
        if(is_dir($fullPath))
            return;

        $wrap = @fopen($fullPath, "a+");
        if(!$wrap)
            return;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $file->getUrl());
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_FILE, $wrap);
        curl_exec($ch);
        curl_close($ch);
        $binary = new Binary();
        $image_manager = $this->getContainer()->get('liip_imagine.cache.resolver.no_cache_web_path');
        $image_manager->store($binary,$fullPath,'');
        if(file_exists($fullPath)){
            $file->setDownloaded(true);
            $this->dm->persist($file);
            $this->dm->flush();
        }
        $this->output->writeln("<info>{$file->getPath()} indirildi.</info>");
    }
} 