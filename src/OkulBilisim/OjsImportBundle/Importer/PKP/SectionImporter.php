<?php

namespace OkulBilisim\OjsImportBundle\Importer\PKP;

use Exception;
use Ojs\JournalBundle\Entity\Section;
use Ojs\JournalBundle\Entity\Journal;
use OkulBilisim\OjsImportBundle\Importer\Importer;

class SectionImporter extends Importer
{
    /**
     * @var array
     */
    private $settings;

    /**
     * @param Journal $journal Section's Journal
     * @param int $oldId Section's ID in the old database
     * @return array
     * @throws Exception
     * @throws \Doctrine\DBAL\ConnectionException
     * @throws \Doctrine\DBAL\DBALException
     */
    public function importJournalsSections($journal, $oldId)
    {
        $this->consoleOutput->writeln("Importing journal's sections...");

        $sectionsSql = "SELECT * FROM sections WHERE journal_id = :id";
        $sectionsStatement = $this->dbalConnection->prepare($sectionsSql);
        $sectionsStatement->bindValue('id', $oldId);
        $sectionsStatement->execute();
        $sections = $sectionsStatement->fetchAll();

        $createdSections = array();
        $persistCounter = 1;

        try {
            $this->em->beginTransaction();

            foreach ($sections as $section) {
                $createdSection = $this->importSection($section['section_id'], $journal);
                $createdSections[$section['section_id']] = $createdSection;
                $persistCounter++;

                if ($persistCounter % 10 == 0) {
                    $this->consoleOutput->writeln("Writing sections...", true);
                    $this->em->flush();
                    $this->em->commit();
                    $this->em->beginTransaction();
                }
            }

            $this->em->flush();
            $this->em->commit();
        }  catch (Exception $exception) {
            $this->em->getConnection()->rollBack();
            throw $exception;
        }

        return $createdSections;
    }

    /**
     * @param int $id Section's ID
     * @param Journal $journal Section's Journal
     * @return Section
     */
    public function importSection($id, $journal)
    {
        $this->consoleOutput->writeln("Reading section #" . $id . "... ", true);

        $sectionSql = "SELECT * FROM sections WHERE section_id = :id LIMIT 1";
        $sectionStatement = $this->dbalConnection->prepare($sectionSql);
        $sectionStatement->bindValue('id', $id);
        $sectionStatement->execute();

        $settingsSql = "SELECT locale, setting_name, setting_value FROM section_settings WHERE section_id = :id";
        $settingsStatement = $this->dbalConnection->prepare($settingsSql);
        $settingsStatement->bindValue('id', $id);
        $settingsStatement->execute();

        $pkpSection = $sectionStatement->fetch();
        $pkpSettings = $settingsStatement->fetchAll();

        foreach ($pkpSettings as $setting) {
            $locale = !empty($setting['locale']) ? $setting['locale'] : 'en_US';
            $name = $setting['setting_name'];
            $value = $setting['setting_value'];
            $this->settings[$locale][$name] = $value;
        }

        $section = new Section();
        $section->setJournal($journal);
        $section->setAllowIndex(!empty($pkpSection['meta_indexed']) ? $pkpSection['meta_indexed'] : 0);
        $section->setHideTitle(!empty($pkpSection['hide_title']) ? $pkpSection['hide_title']: 0);

        foreach ($this->settings as $fieldLocale => $fields) {
            $section->setCurrentLocale(substr($fieldLocale, 0, 2));
            $section->setTitle(!empty($fields['title']) ? $fields['title']: '-');
        }

        $this->consoleOutput->writeln("Writing section #" . $id . "... ", true);

        $this->em->persist($section);

        return $section;
    }
}
