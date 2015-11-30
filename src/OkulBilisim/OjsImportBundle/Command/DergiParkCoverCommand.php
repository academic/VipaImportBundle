<?php

namespace OkulBilisim\OjsImportBundle\Command;

use GuzzleHttp\Exception\BadResponseException;
use Jb\Bundle\FileUploaderBundle\Entity\FileHistory;
use OkulBilisim\OjsImportBundle\Helper\ImportCommand;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use GuzzleHttp\Client;

class DergiParkCoverCommand extends ImportCommand
{
    protected function configure()
    {
        $this
            ->setName('ojs:import:dergipark:covers')
            ->setDescription('Imports DergiPark covers');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $coverless = $this->em
            ->getRepository('OjsJournalBundle:Journal')
            ->findBy(['image' => null]);

        try {
            foreach ($coverless as $journal) {
                $sql = "SELECT journal_id, primary_locale FROM journals WHERE path = :path LIMIT 1";
                $statement = $this->connection->prepare($sql);
                $statement->bindValue('path', $journal->getSlug());
                $statement->execute();
                $result = $statement->fetch();

                if (empty($result)) {
                    continue;
                }

                $id = $result['journal_id'];
                $locale = $result['primary_locale'];

                if ($this->downloadCover($id, $locale)) {
                    $output->writeln("Downloaded a cover for journal #" . $id);
                } else {
                    $output->writeln("Couldn't download a cover for journal #" . $id);
                    continue;
                }

                $filename = "imported/cover/" . $id . ".jpg";

                $history = new FileHistory();
                $history->setFileName($filename);
                $history->setOriginalName($id . "/journalThumbnail_" . $locale . ".jpg");
                $history->setType('journal');

                $journal->setImage($filename);
                $this->em->persist($journal);
                $this->em->persist($history);
                $this->em->flush();
            }
        } catch (Exception $e) {
            $this->logger->error($e->getMessage());
            $this->getContainer()->get('doctrine')->resetManager();
        }
    }

    public function downloadCover($id, $locale)
    {
        $filesystem = new Filesystem();
        $rootDir = $this->getContainer()->get('kernel')->getRootDir();
        $target = sprintf("%s/../web/uploads/journal/imported/cover/%s.jpg", $rootDir, $id);
        $baseUri = sprintf("http://static.dergipark.gov.tr/public/journals/%s/", $id);
        $relativeUri = "journalThumbnail_%s.jpg";

        $client = new Client(['base_uri' => $baseUri]);

        try {
            $response = $client->request('GET', sprintf($relativeUri, $locale));
        } catch (BadResponseException $e) {
            return false;
        }

        if ($response->getStatusCode() === 200 && $response->getHeader('Content-Type')[0] === 'image/jpeg') {
            $body = $response->getBody();

            // Create the directory
            $targetDir = explode('/', $target);
            array_pop($targetDir); // Remove filename
            $targetDir = implode('/', $targetDir);
            $filesystem->mkdir($targetDir);

            $file = fopen($target, "w");
            return fputs($file, $body->getContents());
        }

        return false;
    }
}