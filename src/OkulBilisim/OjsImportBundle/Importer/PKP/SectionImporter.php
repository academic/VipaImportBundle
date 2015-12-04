<?php

namespace OkulBilisim\OjsImportBundle\Importer\PKP;

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
     */
    public function importJournalsSections($journal, $oldId)
    {
        $this->consoleOutput->writeln("Importing journal's sections...");

        $sectionsSql = "SELECT * FROM sections WHERE journal_id = :id";
        $sectionsStatement = $this->connection->prepare($sectionsSql);
        $sectionsStatement->bindValue('id', $oldId);
        $sectionsStatement->execute();

        $sections = $sectionsStatement->fetchAll();
        $createdSections = array();
        foreach ($sections as $section) {
            $createdSections[$section['section_id']] = $this->importSection($section['section_id'], $journal);
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
        $sectionStatement = $this->connection->prepare($sectionSql);
        $sectionStatement->bindValue('id', $id);
        $sectionStatement->execute();

        $settingsSql = "SELECT locale, setting_name, setting_value FROM section_settings WHERE section_id = :id";
        $settingsStatement = $this->connection->prepare($settingsSql);
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

        $this->em->persist($section);
        return $section;
    }
}
