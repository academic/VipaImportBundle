<?php

namespace Ojs\ImportBundle\Importer\PKP;

use DateTime;
use Exception;
use Jb\Bundle\FileUploaderBundle\Entity\FileHistory;
use Ojs\ImportBundle\Entity\ImportMap;
use Ojs\JournalBundle\Entity\Issue;
use Ojs\JournalBundle\Entity\Journal;
use Ojs\JournalBundle\Entity\Section;
use Ojs\ImportBundle\Entity\PendingDownload;
use Ojs\ImportBundle\Importer\Importer;

class IssueImporter extends Importer
{
    /**
     * Imports issues of given journal
     * @param  int $oldJournalId Issue's old Journal ID
     * @param  int $newJournalId Issue's new Journal ID
     * @param  array $sectionIds Sections that the created issue will include
     * @return array An array whose keys are old IDs and values are new IDs
     * @throws Exception
     * @throws \Doctrine\DBAL\DBALException
     */
    public function importJournalIssues($oldJournalId, $newJournalId, $sectionIds)
    {
        $issuesSql = "SELECT * FROM issues WHERE journal_id = :id";
        $issuesStatement = $this->dbalConnection->prepare($issuesSql);
        $issuesStatement->bindValue('id', $oldJournalId);
        $issuesStatement->execute();
        $issues = $issuesStatement->fetchAll();

        return $this->importIssues($issues, $newJournalId, $sectionIds);
    }

    /**
     * @param $issues
     * @param $newJournalId
     * @param $sectionIds
     * @return array
     * @throws Exception
     */
    public function importIssues($issues, $newJournalId, $sectionIds)
    {
        try {
            $this->em->beginTransaction();
            $createdIssues = [];
            $createdIssueIds = [];
            $persistCounter = 0;

            foreach ($issues as $issue) {
                $createdIssue = $this->importIssue($issue['issue_id'], $newJournalId, $sectionIds);
                $createdIssues[$issue['issue_id']] = $createdIssue;
                $persistCounter++;

                if ($persistCounter % 10 == 0 || $persistCounter == count($issues)) {
                    $this->consoleOutput->writeln("Writing issues...", true);
                    $this->em->flush();
                    $this->em->commit();
                    $this->em->clear();
                    $this->em->beginTransaction();

                    /** @var Issue $entity */
                    foreach ($createdIssues as $oldIssueId => $entity) {
                        $createdIssueIds[$oldIssueId] = $entity->getId();
                        $map = new ImportMap($oldIssueId, $entity->getId(), Issue::class);
                        $this->em->persist($map);
                    }

                    $this->em->flush();
                    $createdIssues = [];
                }
            }

            $this->em->commit();
        } catch (Exception $exception) {
            $this->em->rollBack();
            throw $exception;
        }

        /** @var Issue $entity */
        foreach ($createdIssues as $oldIssueId => $entity) {
            $createdIssueIds[$oldIssueId] = $entity->getId();
        }

        return $createdIssueIds;
    }

    /**
     * @param  int $id Issue's ID
     * @param  int $newJournalId new Journal's ID
     * @param  array $sectionIds Journal's section IDs
     * @return Issue Created issue
     * @throws \Doctrine\DBAL\DBALException
     * @throws \Doctrine\ORM\ORMException
     */
    public function importIssue($id, $newJournalId, $sectionIds)
    {
        /** @var Journal $journal */
        $journal = $this->em->getReference('OjsJournalBundle:Journal', $newJournalId);
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
        $settings = [];

        foreach ($pkpSettings as $setting) {
            $locale = !empty($setting['locale']) ? $setting['locale'] : 'en_US';
            $name = $setting['setting_name'];
            $value = $setting['setting_value'];
            $settings[$locale][$name] = $value;
        }

        $issue = new Issue();
        $issue->setJournal($journal);
        $issue->setVolume(is_numeric($pkpIssue['volume']) ? $pkpIssue['volume'] : '');
        $issue->setNumber($pkpIssue['number']);
        $issue->setYear($pkpIssue['year']);
        $issue->setPublished($pkpIssue['published']);
        $issue->setSpecial(false);

        foreach (array_values($sectionIds) as $sectionId) {
            if ($sectionId !== null) {
                /** @var Section $section */
                $section = $this->em->getReference('OjsJournalBundle:Section', $sectionId);
                $issue->addSection($section);
            }
        }

        // In some instances, imported data is not in a proper date format so DateTime::createFromFormat returns false
        // This part handles cases where data_published column is empty or when the data is in a bad format
        $date = false;
        if (!empty($pkpIssue['date_published'])) {
            // This might assign 'false' to the variable
            $date = DateTime::createFromFormat('Y-m-d H:i:s', $pkpIssue['date_published']);
        }
        // Current date & time is used when date is false
        $issue->setDatePublished($date ? $date : new DateTime());

        $cover = null;

        foreach ($settings as $fieldLocale => $fields) {
            if (!$cover && !empty($fields['fileName'])) {
                $cover = $fields['fileName'];
            }

            $issue->setCurrentLocale(substr($fieldLocale, 0, 2));
            $issue->setTitle(!empty($fields['title']) ? $fields['title']: '-');
            $issue->setDescription(!empty($fields['description']) ? $fields['description']: '-');
        }

        if ($cover) {
            $baseDir = '/../web/uploads/journal/imported/';
            $croppedBaseDir = '/../web/uploads/journal/croped/imported/';
            $coverPath = $pkpIssue['journal_id'] . '/' . $cover;

            $pendingDownload = new PendingDownload();
            $pendingDownload
                ->setSource('public/journals/' . $coverPath)
                ->setTarget($baseDir . $coverPath)
                ->setTag('issue-cover');

            $croppedPendingDownload = new PendingDownload();
            $croppedPendingDownload
                ->setSource('public/journals/' . $coverPath)
                ->setTarget($croppedBaseDir . $coverPath)
                ->setTag('issue-cover');

            $history = $this->em->getRepository(FileHistory::class)->findOneBy(['fileName' => 'imported/' . $coverPath]);

            if (!$history) {
                $history = new FileHistory();
                $history->setFileName('imported/' . $coverPath);
                $history->setOriginalName('imported/' . $coverPath);
                $history->setType('journal');
                $this->em->persist($history);
            }

            $this->em->persist($croppedPendingDownload);
            $this->em->persist($pendingDownload);
            $issue->setCover('imported/' . $coverPath);
        }

        $importer = new IssueFileImporter($this->dbalConnection, $this->em, $this->logger, $this->consoleOutput);
        $importer->importIssueFiles($issue, $id, $journal->getSlug());

        $this->em->persist($issue);
        return $issue;
    }
}
