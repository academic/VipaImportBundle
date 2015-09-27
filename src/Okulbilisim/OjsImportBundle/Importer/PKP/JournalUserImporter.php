<?php

namespace Okulbilisim\OjsImportBundle\Importer\PKP;

use Ojs\JournalBundle\Entity\Journal;
use Ojs\JournalBundle\Entity\JournalUser;
use Ojs\UserBundle\Entity\Role;
use Ojs\UserBundle\Entity\User;

class JournalUserImporter extends Importer
{
    /**
     * @param int $newJournalId
     * @param int $oldJournalId
     * @param UserImporter $userImporter
     * @throws \Doctrine\DBAL\DBALException
     */
    public function importJournalUsers($newJournalId, $oldJournalId, $userImporter)
    {
        $journal = $this->em->getRepository('OjsJournalBundle:Journal')->find($newJournalId);

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
            $role = $this->em->getRepository('OjsUserBundle:Role')->findOneBy(['name' => $name]);

            if (!$role) {
                $role = new Role();
                $role->setName($name);
                $role->setRole($name);
            }

            $roleMap[$id] = $role;
        }

        $roleStatement = $this->connection->prepare(
            "SELECT roles.journal_id, roles.user_id, roles.role_id, users.email FROM " .
            "roles JOIN users ON roles.user_id = users.user_id WHERE roles.journal_id" .
            "= :id"
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
                    ->getRepository('OjsUserBundle:User')
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

        foreach ($cache as $item) {
            $this->em->persist($item['journal_user']);
        }

        $this->em->flush();
    }

    private function getJournalUser(&$cache, $email, $journal)
    {
        if (!empty($cache[$email]['journal_user'])) {
            return $cache[$email]['journal_user'];
        }

        $journalUser = new JournalUser();
        $journalUser->setUser($cache[$email]['user']);
        $journalUser->setJournal($journal);
        $cache[$email]['journal_user'] = $journalUser;
        return $cache[$email]['journal_user'];
    }
}