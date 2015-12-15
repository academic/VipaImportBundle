<?php

namespace OkulBilisim\OjsImportBundle\Importer\PKP;

use DateTime;
use Exception;
use Ojs\JournalBundle\Entity\Issue;
use Ojs\JournalBundle\Entity\Journal;
use OkulBilisim\OjsImportBundle\Importer\Importer;

class IssueImporter extends Importer
{
    /**
     * @var array
     */
    private $settings;

    /**
     * @param Journal $journal Issue's Journal
     * @param int $oldId Issue's ID in the old database
     * @param array $sections
     * @return array
     * @throws Exception
     * @throws \Doctrine\DBAL\ConnectionException
     * @throws \Doctrine\DBAL\DBALException
     */
    public function importJournalsIssues($journal, $oldId, $sections)
    {
        $issuesSql = "SELECT * FROM issues WHERE journal_id = :id";
        $issuesStatement = $this->dbalConnection->prepare($issuesSql);
        $issuesStatement->bindValue('id', $oldId);
        $issuesStatement->execute();
        $issues = $issuesStatement->fetchAll();

        try {
            $this->em->beginTransaction();
            $createdIssues = array();
            $persistCounter = 1;

            foreach ($issues as $issue) {
                $createdIssue = $this->importIssue($issue['issue_id'], $journal, $sections);
                $createdIssues[$issue['issue_id']] = $createdIssue;
                $persistCounter++;

                if ($persistCounter % 10 == 0 || $persistCounter == count($issues)) {
                    $this->consoleOutput->writeln("Writing issues...", true);
                    $this->em->flush();
                    $this->em->commit();
                    $this->em->beginTransaction();
                }
            }

            $this->em->flush();
            $this->em->commit();
        } catch (Exception $exception) {
            $this->em->rollBack();
            throw $exception;
        }

        return $createdIssues;
    }

    /**
     * @param int     $id       Issue's ID
     * @param Journal $journal  Issue's Journal
     * @param array   $sections Journal sections
     * @return Issue
     */
    public function importIssue($id, $journal, $sections)
    {
        $this->consoleOutput->writeln("Reading issue #" . $id . "... ", true);

        $issueSql = "SELECT * FROM issues WHERE issue_id = :id LIMIT 1";
        $issueStatement = $this->dbalConnection->prepare($issueSql);
        $issueStatement->bindValue('id', $id);
        $issueStatement->execute();

        $settingsSql = "SELECT locale, setting_name, setting_value FROM issue_settings WHERE issue_id = :id";
        $settingsStatement = $this->dbalConnection->prepare($settingsSql);
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
        $issue->setSpecial(false);

        foreach (array_values($sections) as $section) {
            $issue->addSection($section);
        }

        // In some instances, imported data is not in a proper date format so DateTime::createFromFormat returns false
        // This part handles cases where data_published column is empty or when the data is in a bad format
        $date = false;
        if (!empty($pkpIssue['date_published'])) {
            // This might assign 'false' to the variable
            $date = DateTime::createFromFormat('Y-m-d h:m:s', $pkpIssue['date_published']);
        }
        // Current date & time is used when date is false
        $issue->setDatePublished($date ? $date : new DateTime());

        foreach ($this->settings as $fieldLocale => $fields) {
            $issue->setCurrentLocale(substr($fieldLocale, 0, 2));
            $issue->setTitle(!empty($fields['title']) ? $fields['title']: '-');
            $issue->setDescription(!empty($fields['description']) ? $fields['description']: '-');
        }

        $importer = new IssueFileImporter($this->dbalConnection, $this->em, $this->logger, $this->consoleOutput);
        $importer->importIssueFiles($issue, $id, $journal->getSlug());

        $this->em->persist($issue);
        return $issue;
    }
}