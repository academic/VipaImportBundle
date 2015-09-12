<?php

namespace Okulbilisim\OjsImportBundle\Command;

use DateTime;
use Ojs\JournalBundle\Entity\Journal;
use Ojs\JournalBundle\Entity\JournalTranslation;
use Ojs\JournalBundle\Entity\Lang;
use Ojs\JournalBundle\Entity\Publisher;
use Ojs\JournalBundle\Entity\PublisherTranslation;
use Okulbilisim\OjsImportBundle\Helper\ImportCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PkpOjsJournalCommand extends ImportCommand
{
    /**
     * @var Journal
     */
    private $journal;

    /**
     * @var array
     */
    private $settings;

    protected function configure()
    {
        $this
            ->setName('ojs:import:pkp:journal')
            ->setDescription('Import an user from PKP/OJS')
            ->addArgument('id', InputArgument::REQUIRED, 'Journal ID');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);
        $id = $input->getArgument('id');
        $this->importJournal($id);
    }

    private function importJournal($id)
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

        foreach ($pkpSettings as $setting) {
            $locale = !empty($setting['locale']) ? $setting['locale'] : $primaryLocale;
            $name = $setting['setting_name'];
            $value = $setting['setting_value'];
            $this->settings[$locale][$name] = $value;
        }

        $this->journal = new Journal();
        $this->journal->setCurrentLocale($primaryLocale);
        $this->journal->setSlug($pkpJournal['path']);

        // Fill translatable fields in all available languages
        foreach ($this->settings as $fieldLocale => $fields) {
            $translation = new JournalTranslation();
            $translation->setLocale(substr($fieldLocale, 0, 2));

            isset($fields['title']) ?
                $translation->setTitle($fields['title']) :
                $translation->setTitle('Unknown Journal');

            isset($fields['description']) ?
                $translation->setDescription($fields['description']) :
                $translation->setDescription('A Journal');

            $this->journal->addTranslation($translation);
        }

        isset($this->settings[$primaryLocale]['printIssn']) ?
            $this->journal->setIssn($this->settings[$primaryLocale]['printIssn']) :
            $this->journal->setIssn('1234-5679');

        isset($this->settings[$primaryLocale]['onlineIssn']) ?
            $this->journal->setEissn($this->settings[$primaryLocale]['onlineIssn']) :
            $this->journal->setEissn('1234-5679');

        $date = sprintf('%d-01-01 00:00:00',
            isset($this->settings[$primaryLocale]['initialYear']) ?
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

        $this->em->persist($this->journal);
        $this->em->flush();
    }

    private function importAndSetPublisher($name, $locale)
    {
        $publisher = $this->em
            ->getRepository('OjsJournalBundle:Publisher')
            ->findOneBy(['name' => $name]);

        if (!$publisher) {
            $url = isset($this->settings[$locale]['publisherUrl']) ? $this->settings[$locale]['publisherUrl'] : null;
            $publisher = $this->createPublisher($this->settings[$locale]['publisherInstitution'], $url);

            foreach ($this->settings as $fieldLocale => $fields) {
                $translation = new PublisherTranslation();
                $translation->setLocale(substr($fieldLocale, 0, 2));

                isset($fields['publisherNote']) ?
                    $translation->setAbout($fields['publisherNote']) :
                    $translation->setAbout('A journal');

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
        $publisher->setAbout('A publisher');

        $this->em->persist($publisher);
        $this->em->flush();

        return $publisher;
    }

    private function createPublisher($name, $url)
    {
        $publisher = new Publisher();
        $publisher->setName($name);
        $publisher->setEmail('publisher@example.com');
        $publisher->setAddress('123 Example Street, Exampletown, EX');
        $publisher->setPhone('+1 234 567 89 01');
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
        isset($nameMap[$code]) ?
            $lang->setName($nameMap[$code]) :
            $lang->setName('Unknown Language');

        $this->em->persist($lang);
        $this->em->flush();

        return $lang;
    }
}