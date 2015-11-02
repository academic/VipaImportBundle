<?php

namespace OkulBilisim\OjsImportBundle\Command;

use Behat\Transliterator\Transliterator;
use Ojs\JournalBundle\Entity\Subject;
use Ojs\JournalBundle\Entity\SubjectTranslation;
use OkulBilisim\OjsImportBundle\Helper\ImportCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PkpImportSubjectsCommand extends ImportCommand
{
    protected function configure()
    {
        $this
            ->setName('ojs:import:pkp:subjects')
            ->setDescription('Import subjects from PKP/OJS');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);
        $allJournals = $this->em->getRepository('OjsJournalBundle:Journal')->findAll();

        foreach ($allJournals as $journal) {
            $settingSql = "SELECT journal_settings.setting_value FROM journal_settings JOIN journals ON" .
                " journals.journal_id = journal_settings.journal_id WHERE journals.path = :path AND" .
                " journal_settings.setting_name = 'categories'";

            $settingStatement = $this->connection->prepare($settingSql);
            $settingStatement->bindValue('path', $journal->getSlug());
            $settingStatement->execute();

            $settingResult = $settingStatement->fetch();

            if ($settingResult) {
                $categories = unserialize($settingResult['setting_value']);
                foreach($categories as $categoryId) {
                    $categorySql = "SELECT locale, setting_value FROM " .
                        "controlled_vocab_entry_settings WHERE " .
                        "controlled_vocab_entry_id = 5000000112";

                    $categoryStatement = $this->connection->prepare($categorySql);
                    $categoryStatement->execute();

                    $categoryResult = $categoryStatement->fetchAll();
                    if (!empty($categoryResult)) {
                        $slug = Transliterator::urlize($categoryResult[0]['setting_value']);

                        $subject = $this->em
                            ->getRepository('OjsJournalBundle:Subject')
                            ->findOneBy(['slug' => $slug]);

                        if (!$subject) {
                            $subject = new Subject();
                            $subject->setSlug($slug);
                            $subject->setCurrentLocale('en');

                            foreach ($categoryResult as $translation) {
                                $subjectTranslation = new SubjectTranslation();
                                $subjectTranslation->setLocale(substr($translation['locale'], 0, 2));
                                $subjectTranslation->setSubject($translation['setting_value']);
                                $subjectTranslation->setTranslatable($subject);
                                $this->em->persist($subjectTranslation);
                            }

                            $journal->addSubject($subject);
                            $this->em->persist($journal);
                            $this->em->persist($subject);
                            $this->em->flush();
                        } elseif (!$journal->getSubjects()->contains($subject)) {
                            $journal->addSubject($subject);
                            $this->em->persist($journal);
                            $this->em->flush();
                        }
                    }
                }
            }
        }
    }

}