<?php

namespace OkulBilisim\OjsImportBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Doctrine\ORM\EntityManager;
use Symfony\Component\Filesystem\Filesystem;

class DownloadCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('ojs:import:download')
            ->setDescription('Downloads files of imported entities')
            ->addArgument('host', InputArgument::REQUIRED, 'Hostname of the server where the files are stored');
    }


    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var EntityManager $em */
        $em = $this->getContainer()->get('doctrine.orm.entity_manager');
        $pendingDownloads = $em->getRepository('OkulBilisimOjsImportBundle:PendingDownload')->findAll();
        $output->writeln("Downloading...");

        foreach ($pendingDownloads as $download) {
            $output->writeln("Downloading " . $download->getSource());
            $status = $this->download($input->getArgument('host'), $download->getSource(), $download->getTarget());
            $status ? $em->remove($download) : $output->writeln("Couldn't download " . $download->getSource());
            $em->flush($download);
        }
    }

    private function download($host, $source, $target)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $host . '/' . $source);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_BINARYTRANSFER, true);

        $data = curl_exec($curl);
        curl_close($curl);

        $rootDir = $this->getContainer()->get('kernel')->getRootDir();
        $fs = new Filesystem();

        $targetDir = explode('/', $target);
        array_pop($targetDir);
        $targetDir = implode('/', $targetDir);
        $fs->mkdir($rootDir . '/'. $targetDir);

        $file = fopen($rootDir . $target, "w");
        $status = fputs($file, $data);
        fclose($file);

        return $status;
    }
}