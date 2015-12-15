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
     * @param int $oldJournalId Section's old Journal ID
     * @param int $newJournalId Section's new Journal ID
     * @return array An array whose keys are old IDs and values are new IDs
     * @throws Exception
     * @throws \Doctrine\DBAL\DBALException
     */
    public function importJournalSections($oldJournalId, $newJournalId)
    {
        $this->consoleOutput->writeln("Importing journal's sections...");

        $sectionsSql = "SELECT * FROM sections WHERE journal_id = :id";
        $sectionsStatement = $this->dbalConnection->prepare($sectionsSql);
        $sectionsStatement->bindValue('id', $oldJournalId);
        $sectionsStatement->execute();
        $sections = $sectionsStatement->fetchAll();

        try {
            $this->em->beginTransaction();
            $createdSections = array();
            $persistCounter = 1;

            foreach ($sections as $section) {
                $createdSection = $this->importSection($section['section_id'], $newJournalId);
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
            $this->em->rollBack();
            throw $exception;
        }

        $createdSectionIds = array();

        /** @var Section $entity */
        foreach ($createdSections as $oldJournalId => $entity) {
            $createdSectionIds[$oldJournalId] = $entity->getId();
        }

        return $createdSectionIds;
    }

    /**
     * @param int $id Section's ID
     * @param int $newJournalId Section's Journal ID
     * @return Section
     */
    public function importSection($id, $newJournalId)
    {
        /** @var Journal $journal */
        $journal = $this->em->getReference('OjsJournalBundle:Journal', $newJournalId);
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
