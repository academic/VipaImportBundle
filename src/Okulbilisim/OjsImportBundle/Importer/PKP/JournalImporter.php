<?php

namespace Okulbilisim\OjsImportBundle\Importer\PKP;

use DateTime;
use Ojs\JournalBundle\Entity\Journal;
use Ojs\JournalBundle\Entity\JournalTranslation;
use Ojs\JournalBundle\Entity\Lang;
use Ojs\JournalBundle\Entity\Publisher;
use Ojs\JournalBundle\Entity\PublisherTranslation;

class JournalImporter extends Importer
{
    /**
     * @var Journal
     */
    private $journal;

    /**
     * @var array
     */
    private $settings;

    public function importJournal($id)
    {
        $journalSql = "SELECT path, primary_locale FROM journals WHERE journal_id = :id LIMIT 1";
        $journalStatement = $this->connection->prepare($journalSql);
        $journalStatement->bindValue('id', $id);
        $journalStatement->execute();

        $settingsSql = "SELECT locale, setting_name, setting_value FROM journal_settings WHERE journal_id = :id";
        $settingsStatement = $this->connection->prepare($settingsSql);
        $settingsStatement->bindValue('id', $id);
        $settingsStatement->execute();

        $pkpJournal = $journalStatement->fetch();
        $pkpSettings = $settingsStatement->fetchAll();
        $primaryLocale = $pkpJournal['primary_locale'];
        $languageCode = substr($primaryLocale, 0, 2);

        !$pkpJournal && die('Journal not found.' . PHP_EOL);

        foreach ($pkpSettings as $setting) {
            $locale = !empty($setting['locale']) ? $setting['locale'] : $primaryLocale;
            $name = $setting['setting_name'];
            $value = $setting['setting_value'];
            $this->settings[$locale][$name] = $value;
        }

        $this->journal = new Journal();
        $this->journal->setStatus(1);
        $this->journal->setPublished(true);
        $this->journal->setSlug($pkpJournal['path']);

        // Fill translatable fields in all available languages
        foreach ($this->settings as $fieldLocale => $fields) {
            $translation = new JournalTranslation();
            $translation->setLocale(substr($fieldLocale, 0, 2));
            $this->journal->setCurrentLocale(substr($fieldLocale, 0, 2));

            !empty($fields['title']) ?
                $translation->setTitle($fields['title']) :
                $translation->setTitle('Unknown Journal');

            !empty($fields['description']) ?
                $translation->setDescription($fields['description']) :
                $translation->setDescription('-');

            $this->journal->addTranslation($translation);
        }

        $this->journal->setCurrentLocale($primaryLocale);

        !empty($this->settings[$primaryLocale]['printIssn']) ?
            $this->journal->setIssn($this->settings[$primaryLocale]['printIssn']) :
            $this->journal->setIssn('1234-5679');

        !empty($this->settings[$primaryLocale]['onlineIssn']) ?
            $this->journal->setEissn($this->settings[$primaryLocale]['onlineIssn']) :
            $this->journal->setEissn('1234-5679');

        $date = sprintf('%d-01-01 00:00:00',
            !empty($this->settings[$primaryLocale]['initialYear']) ?
                $this->settings[$primaryLocale]['initialYear'] : '2015');
        $this->journal->setFounded(DateTime::createFromFormat('Y-m-d H:i:s', $date));

        // Set publisher
        !empty($this->settings[$primaryLocale]['publisherInstitution']) ?
            $this->importAndSetPublisher($this->settings[$primaryLocale]['publisherInstitution'], $primaryLocale) :
            $this->journal->setPublisher($this->getUnknownPublisher());

        // Use existing languages or create if needed
        $language = $this->em
            ->getRepository('OjsJournalBundle:Lang')
            ->findOneBy(['code' => $languageCode]);
        $this->journal->setMandatoryLang($language ? $language : $this->createLanguage($languageCode));
        $this->journal->addLanguage($language ? $language : $this->createLanguage($languageCode));

        $sectionImporter = new SectionImporter($this->connection, $this->em);
        $createdSections = $sectionImporter->importJournalsSections($this->journal, $id);

        $issueImporter = new IssueImporter($this->connection, $this->em);
        $issueImporter->importJournalsIssues($this->journal, $id, $createdSections);

        $this->em->persist($this->journal);
        $this->em->flush();

    }

    private function importAndSetPublisher($name, $locale)
    {
        $publisher = $this->em
            ->getRepository('OjsJournalBundle:Publisher')
            ->findOneBy(['name' => $name]);

        if (!$publisher) {
            $url = !empty($this->settings[$locale]['publisherUrl']) ? $this->settings[$locale]['publisherUrl'] : null;
            $publisher = $this->createPublisher($this->settings[$locale]['publisherInstitution'], $url);

            foreach ($this->settings as $fieldLocale => $fields) {
                $translation = new PublisherTranslation();
                $translation->setLocale(substr($fieldLocale, 0, 2));

                !empty($fields['publisherNote']) ?
                    $translation->setAbout($fields['publisherNote']) :
                    $translation->setAbout('-');

                $publisher->addTranslation($translation);
            }
        }

        $this->journal->setPublisher($publisher);
    }

    private function getUnknownPublisher()
    {
        $publisher = $this->em
            ->getRepository('OjsJournalBundle:Publisher')
            ->findOneBy(['name' => 'Unknown Publisher']);

        !$publisher && $publisher = $this->createPublisher('Unknown Publisher', 'http://example.com');
        $publisher->setCurrentLocale('en');
        $publisher->setAbout('-');

        $this->em->persist($publisher);
        $this->em->flush();

        return $publisher;
    }

    private function createPublisher($name, $url)
    {
        $publisher = new Publisher();
        $publisher->setName($name);
        $publisher->setEmail('publisher@example.com');
        $publisher->setAddress('-');
        $publisher->setPhone('-');
        $publisher->setUrl($url);

        $this->em->persist($publisher);
        $this->em->flush();

        return $publisher;
    }

    private function createLanguage($code)
    {
        $nameMap = array(
            'tr' => 'Türkçe',
            'en' => 'English',
            'de' => 'Deutsch',
            'fr' => 'Français',
            'ru' => 'Русский язык',
        );

        $lang = new Lang();
        $lang->setCode($code);
        !empty($nameMap[$code]) ?
            $lang->setName($nameMap[$code]) :
            $lang->setName('Unknown Language');

        $this->em->persist($lang);
        $this->em->flush();

        return $lang;
    }
}