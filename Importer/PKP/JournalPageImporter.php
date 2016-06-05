<?php

namespace Ojs\ImportBundle\Importer\PKP;

use Ojs\JournalBundle\Entity\JournalPage;
use Ojs\ImportBundle\Importer\Importer;

class JournalPageImporter extends Importer
{
    public function importPages($oldJournalId, $newJournalId)
    {
        $pagesSql = "SELECT static_page_id FROM static_pages WHERE journal_id = :id";
        $pagesStatement = $this->dbalConnection->prepare($pagesSql);
        $pagesStatement->bindValue('id', $oldJournalId);
        $pagesStatement->execute();
        $results = $pagesStatement->fetchAll();

        foreach ($results as $result) {
            $page = $this->importPage($result['static_page_id'], $newJournalId);
            $this->em->beginTransaction();
            $this->em->persist($page);
            $this->em->flush();
            $this->em->commit();
            $this->em->clear();
        }
    }

    public function importPage($id, $newJournalId)
    {
        $settingsSql = "SELECT setting_name, setting_value, locale FROM static_page_settings WHERE static_page_id = :id";
        $settingsStatement = $this->dbalConnection->prepare($settingsSql);
        $settingsStatement->bindValue('id', $id);
        $settingsStatement->execute();

        $pkpSettings = $settingsStatement->fetchAll();
        $settings = [];

        foreach ($pkpSettings as $setting) {
            $locale = !empty($setting['locale']) ? $setting['locale'] : 'en_US';
            $name = $setting['setting_name'];
            $value = $setting['setting_value'];
            $settings[$locale][$name] = $value;
        }

        $journal = $this->em->getReference('OjsJournalBundle:Journal', $newJournalId);

        if (!$journal) {
            return null;
        }

        $page = new JournalPage();
        $page->setJournal($journal);

        foreach ($settings as $locale => $fields) {
            $title = !empty($fields['title']) ? $fields['title'] : 'Page';
            $content = !empty($fields['content']) ? $fields['content'] : 'This page is intentionally left blank.';

            $page->setCurrentLocale(mb_substr($locale, 0, 2));
            $page->setTitle($title);
            $page->setBody($content);
        }

        return $page;
    }
}
