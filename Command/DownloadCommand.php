<?php

namespace Ojs\ImportBundle\Command;

use Doctrine\ORM\EntityManager;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class DownloadCommand extends ContainerAwareCommand
{
    /**
     * @var OutputInterface
     */
    private $output;

    protected function configure()
    {
        $this
            ->setName('ojs:import:download')
            ->setDescription('Downloads files of imported entities')
            ->addArgument('host', InputArgument::REQUIRED, 'Hostname of the server where the files are stored')
            ->addArgument('tag', InputArgument::OPTIONAL, 'Tag of the files which will be downloaded ' .
                '(eg. issue-cover). Tagless ones will be downloaded if there is not any tag supplied')
            ->addOption('retry', null, InputOption::VALUE_NONE, 'Retry failed downloads');
    }


    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->output = $output;

        /** @var EntityManager $em */
        $em = $this->getContainer()->get('doctrine.orm.entity_manager');
        $pendingDownloads = $em
            ->getRepository('ImportBundle:PendingDownload')
            ->findBy([
                'tag' => $input->getArgument('tag'),
                'error' => $input->getOption('retry'),
            ]);
        $this->output->writeln("Downloading...");

        foreach ($pendingDownloads as $download) {
            $successful = $this->download($input->getArgument('host'), $download->getSource(), $download->getTarget());

            if ($successful) {
                $em->remove($download);
            } else {
                $download->setError(true);
                $this->output->writeln("Couldn't download " . $download->getSource());
            }

            $em->flush($download);
        }
    }

    private function download($host, $source, $target)
    {
        try {
            $progress = new ProgressBar($this->output, 100);
            $progress->start();

            $client = new Client(['base_uri' => $host]);
            $response = $client->request('GET', $source, [
                'progress' => function ($total, $downloaded) use ($progress) {
                    $progress->setProgress(100 * $downloaded / ++$total);
                }
            ]);
        } catch (BadResponseException $e) {
            return false;
        }

        if ($response->getStatusCode() === 200) {
            $rootDir = $this->getContainer()->get('kernel')->getRootDir();
            $absoluteTarget = $rootDir . $target;
            $body = $response->getBody();

            $targetDir = explode('/', $absoluteTarget);
            array_pop($targetDir); // Remove filename
            $targetDir = implode('/', $targetDir);


            $filesystem = new Filesystem();
            $filesystem->mkdir($targetDir);
            $file = fopen($absoluteTarget, "w");

            return fputs($file, $body->getContents());
        }

        return false;
    }
}
