<?php

namespace Ojs\ImportBundle\Command;

use Doctrine\ORM\EntityManager;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class DownloadCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('ojs:import:download')
            ->setDescription('Downloads files of imported entities')
            ->addArgument('host', InputArgument::REQUIRED, 'Hostname of the server where the files are stored')
            ->addArgument('tag', InputArgument::OPTIONAL, 'Tag of the files which will be downloaded ' .
                '(eg. issue-cover). Tagless ones will be downloaded if there is not any tag supplied');
    }


    protected function execute(InputInterface $input, OutputInterface $output)
    {
        /** @var EntityManager $em */
        $em = $this->getContainer()->get('doctrine.orm.entity_manager');
        $pendingDownloads = $em
            ->getRepository('ImportBundle:PendingDownload')
            ->findBy(['tag' => $input->getArgument('tag')]);
        $output->writeln("Downloading...");

        foreach ($pendingDownloads as $download) {
            $output->writeln("Downloading " . $download->getSource());
            $successful = $this->download($input->getArgument('host'), $download->getSource(), $download->getTarget());

            if ($successful) {
                $em->remove($download);
                $em->flush($download);
            } else {
                $output->writeln("Couldn't download " . $download->getSource());
            }
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
