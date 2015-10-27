<?php

namespace OkulBilisim\OjsImportBundle\Command;

use Jb\Bundle\FileUploaderBundle\Entity\FileHistory;
use OkulBilisim\OjsImportBundle\Helper\ImportCommand;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class DergiParkCoverCommand extends ImportCommand
{
    protected function configure()
    {
        $this
            ->setName('ojs:import:dergipark:covers');
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
        $url = "http://static.dergipark.gov.tr/public/journals/" . $id . "/journalThumbnail_" . $locale . ".jpg";
        $target = sprintf('/../web/uploads/journal/imported/cover/%s.jpg', $id);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
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