<?php

namespace Ojs\ImportBundle\Importer\PKP;

use Ojs\JournalBundle\Entity\Board;
use Ojs\ImportBundle\Importer\Importer;

class BoardImporter extends Importer
{
    public function importBoards($oldJournalId, $newJournalId)
    {
        $groupSql = "SELECT group_id, assoc_id FROM groups WHERE assoc_id = :id";
        $groupStatement = $this->dbalConnection->prepare($groupSql);
        $groupStatement->bindValue('id', $oldJournalId);
        $groupStatement->execute();
        $groups = $groupStatement->fetchAll();

        $boardIds = array();

        foreach ($groups as $group) {
            $board = $this->importBoard($group['group_id'], $newJournalId);

            if ($board) {
                $this->em->beginTransaction();
                $this->em->persist($board);
                $this->em->flush();
                $this->em->commit();
                $this->em->clear();

                $boardIds[$group['group_id']] = $board->getId();
            }
        }

        return $boardIds;
    }

    public function importBoard($id, $newJournalId)
    {
        $settingsSql = "SELECT setting_value, locale FROM group_settings WHERE group_id = :id";
        $settingsStatement = $this->dbalConnection->prepare($settingsSql);
        $settingsStatement->bindValue('id', $id);
        $settingsStatement->execute();
        $results = $settingsStatement->fetchAll();

        if (count($results) == 0) {
            return null;
        }

        $journal = $this->em->getReference('OjsJournalBundle:Journal', $newJournalId);

        $board = new Board();
        $board->setJournal($journal);

        foreach ($results as $result) {
            $board->setCurrentLocale(substr($result['locale'], 0, 2));
            $board->setName($result['setting_value']);
        }

        return $board;
    }
}
