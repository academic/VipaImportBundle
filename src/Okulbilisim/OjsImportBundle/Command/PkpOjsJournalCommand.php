<?php

namespace Okulbilisim\OjsImportBundle\Command;

use DateTime;
use Ojs\JournalBundle\Entity\Journal;
use Ojs\JournalBundle\Entity\Lang;
use Okulbilisim\OjsImportBundle\Helper\ImportCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PkpOjsJournalCommand extends ImportCommand
{
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
        $settings = array();

        foreach ($pkpSettings as $setting) {
            $locale = $setting['locale'];
            $name = $setting['setting_name'];
            $value = $setting['setting_value'];
            $settings[$locale][$name] = $value;
        }

        $primaryLocale = $pkpJournal['primary_locale'];
        $languageCode = substr($primaryLocale, 0, 2);
        $language = $this->em
            ->getRepository('OjsJournalBundle:Lang')
            ->findOneBy(['code' => $languageCode]);

        $journal = new Journal();
        $journal->setCurrentLocale($languageCode);

        var_dump($settings[$primaryLocale]);

        isset($settings[$primaryLocale]['title']) ?
            $journal->setTitle($settings[$primaryLocale]['title']) :
            $journal->setTitle('Unknown Journal');

        isset($settings[$primaryLocale]['description']) ?
            $journal->setDescription($settings[$primaryLocale]['description']) :
            $journal->setDescription('A Journal');

        isset($settings[$primaryLocale]['printIssn']) ?
            $journal->setIssn($settings[$primaryLocale]['printIssn']) :
            $journal->setIssn('1234-5679');

        isset($settings[$primaryLocale]['onlineIssn']) ?
            $journal->setEissn($settings[$primaryLocale]['onlineIssn']) :
            $journal->setEissn('1234-5679');

        $journal->setMandatoryLang($language ? $language : $this->createLanguage($languageCode));
        $journal->addLanguage($language ? $language : $this->createLanguage($languageCode));

        $date = sprintf('%d-01-01 00:00:00',
            isset($settings[$primaryLocale]['initialYear']) ?
                $settings[$primaryLocale]['initialYear'] : '2015');
        $journal->setFounded(DateTime::createFromFormat('Y-m-d H:i:s', $date));

        $this->em->persist($journal);
        $this->em->flush();
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