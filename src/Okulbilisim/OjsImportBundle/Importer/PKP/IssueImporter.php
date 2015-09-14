<?php

namespace Okulbilisim\OjsImportBundle\Importer\PKP;

use DateTime;
use Ojs\JournalBundle\Entity\Issue;
use Ojs\JournalBundle\Entity\IssueTranslation;
use Ojs\JournalBundle\Entity\Journal;
use Symfony\Component\Validator\Constraints\Date;

class IssueImporter extends Importer
{
    /**
     * @var array
     */
    private $settings;

    /**
     * @param Journal $journal Issue's Journal
     */
    public function importJournalsIssues($journal, $oldId)
    {
        $issuesSql = "SELECT * FROM issues WHERE journal_id = :id";
        $issuesStatement = $this->connection->prepare($issuesSql);
        $issuesStatement->bindValue('id', $oldId);
        $issuesStatement->execute();

        $issues = $issuesStatement->fetchAll();
        foreach ($issues as $issue) {
            $this->importIssue($issue['issue_id'], $journal);
        }
    }

    /**
     * @param int $id Issue's ID
     * @param Journal $journal Issue's Journal
     * @return Issue
     */
    public function importIssue($id, $journal)
    {
        $issueSql = "SELECT * FROM issues WHERE issue_id = :id LIMIT 1";
        $issueStatement = $this->connection->prepare($issueSql);
        $issueStatement->bindValue('id', $id);
        $issueStatement->execute();

        $settingsSql = "SELECT locale, setting_name, setting_value FROM issue_settings WHERE issue_id = :id";
        $settingsStatement = $this->connection->prepare($settingsSql);
        $settingsStatement->bindValue('id', $id);
        $settingsStatement->execute();

        $pkpIssue = $issueStatement->fetch();
        $pkpSettings = $settingsStatement->fetchAll();

        foreach ($pkpSettings as $setting) {
            $locale = !empty($setting['locale']) ? $setting['locale'] : 'en_US';
            $name = $setting['setting_name'];
            $value = $setting['setting_value'];
            $this->settings[$locale][$name] = $value;
        }

        $issue = new Issue();
        $issue->setJournal($journal);
        $issue->setVolume($pkpIssue['volume']);
        $issue->setNumber($pkpIssue['number']);
        $issue->setYear($pkpIssue['year']);
        $issue->setPublished($pkpIssue['published']);
        $issue->setDatePublished(
            !empty($pkpIssue['date_published']) ?
                DateTime::createFromFormat('Y-m-d h:m:s', $pkpIssue['date_published']) :
                new DateTime() // Use current date and time if publishing date is not defined
        );

        foreach ($this->settings as $fieldLocale => $fields) {
            $translation = new IssueTranslation();
            $translation->setLocale(substr($fieldLocale, 0, 2));
            $issue->setCurrentLocale(substr($fieldLocale, 0, 2));

            $translation->setTitle(!empty($fields['title']) ? $fields['title']: '-');
            $translation->setDescription(!empty($fields['description']) ? $fields['description']: '-');
            $issue->addTranslation($translation);
        }

        $this->em->persist($issue);

        return $issue;
    }
}