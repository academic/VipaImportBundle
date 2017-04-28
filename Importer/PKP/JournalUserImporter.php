<?php

namespace Vipa\ImportBundle\Importer\PKP;

use Vipa\JournalBundle\Entity\Journal;
use Vipa\JournalBundle\Entity\JournalUser;
use Vipa\UserBundle\Entity\Role;
use Vipa\UserBundle\Entity\User;
use Vipa\ImportBundle\Helper\ImportHelper;
use Vipa\ImportBundle\Importer\Importer;

class JournalUserImporter extends Importer
{
    /**
     * Imports users of the given journal
     * @param int $newJournalId New journal's ID
     * @param int $oldJournalId Old journal's ID
     * @param UserImporter $userImporter User importer class
     * @throws \Doctrine\DBAL\DBALException
     */
    public function importJournalUsers($newJournalId, $oldJournalId, $userImporter)
    {
        $this->em->clear();
        $journal = $this->em->getRepository('VipaJournalBundle:Journal')->find($newJournalId);

        $roleMap = [
            '1'      => "ROLE_ADMIN",
            '2'      => "ROLE_USER",
            '10'     => "ROLE_JOURNAL_MANAGER",
            '100'    => "ROLE_EDITOR",
            '200'    => 'ROLE_SECTION_EDITOR',
            '300'    => 'ROLE_LAYOUT_EDITOR',
            '1000'   => "ROLE_REVIEWER",
            '2000'   => 'ROLE_COPYEDITOR',
            '3000'   => "ROLE_PROOFREADER",
            '10000'  => "ROLE_AUTHOR",
            '100000' => 'ROLE_READER',
            '200000' => "ROLE_SUBSCRIPTION_MANAGER",
        ];

        // Replace role names with role entities
        foreach ($roleMap as $id => $name) {
            $role = $this->em->getRepository('VipaUserBundle:Role')->findOneBy(['role' => $name]);

            if (!$role) {
                $role = new Role();
                $role->setName($name);
                $role->setRole($name);
            }

            $roleMap[$id] = $role;
        }

        $roleStatement = $this->dbalConnection->prepare(
            "SELECT roles.journal_id, roles.user_id, roles.role_id, users.email FROM " .
            "roles JOIN users ON roles.user_id = users.user_id WHERE roles.journal_id" .
            "= :id " . ImportHelper::spamUsersFilterSql()
        );

        $roleStatement->bindValue('id', $oldJournalId);
        $roleStatement->execute();
        $roles = $roleStatement->fetchAll();

        $cache = array();

        foreach ($roles as $role) {
            $email = $role['email'];

            // Put the user from DB to cache
            if (empty($cache[$email]['user'])) {
                $cache[$email]['user'] = $this->em
                    ->getRepository('VipaUserBundle:User')
                    ->findOneBy(['email' => $email]);
            }

            // Create the user and put it to cache
            if(empty($cache[$email]['user'])) {
                $cache[$email]['user'] = $userImporter
                    ->importUser($role['user_id'], false);
            }

            /** @var JournalUser $journalUser */
            $journalUser = $this->getJournalUser($cache, $email, $journal);
            $journalUser->addRole($roleMap[dechex($role['role_id'])]);
        }

        $this->consoleOutput->writeln("Writing data...");
        $this->em->flush();
        $this->consoleOutput->writeln("Imported users.");
    }

    /**
     * Fetches the journal user
     * @param array $cache User cache
     * @param String $email User's email
     * @param Journal $journal Journal
     * @return JournalUser Imported or retrieved JournalUser
     */
    private function getJournalUser(&$cache, $email, $journal)
    {
        if (!empty($cache[$email]['journal_user'])) {
            return $cache[$email]['journal_user'];
        }

        $journalUser = $this->em
            ->getRepository('VipaJournalBundle:JournalUser')
            ->findOneBy(['journal' => $journal, 'user' => $cache[$email]['user']]);

        if ($journalUser === null) {
            $journalUser = new JournalUser();
            $journalUser->setUser($cache[$email]['user']);
            $journalUser->setJournal($journal);
            $this->em->persist($journalUser);
        }

        $cache[$email]['journal_user'] = $journalUser;
        return $cache[$email]['journal_user'];
    }
}
