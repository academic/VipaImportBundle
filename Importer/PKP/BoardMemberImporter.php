<?php

namespace Vipa\ImportBundle\Importer\PKP;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Vipa\JournalBundle\Entity\BoardMember;
use Vipa\ImportBundle\Importer\Importer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;

class BoardMemberImporter extends Importer
{
    /**
     * @var UserImporter
     */
    private $ui;

    /**
     * ArticleImporter constructor.
     * @param Connection $dbalConnection
     * @param EntityManager $em
     * @param OutputInterface $consoleOutput
     * @param LoggerInterface $logger
     * @param UserImporter $ui
     */
    public function __construct(
        Connection $dbalConnection,
        EntityManager $em,
        LoggerInterface $logger,
        OutputInterface $consoleOutput,
        UserImporter $ui
    )
    {
        parent::__construct($dbalConnection, $em, $logger, $consoleOutput);
        $this->ui = $ui;
    }

    public function importBoardMembers($oldBoardId, $newBoardId)
    {
        $groupMembersSql = "SELECT user_id, seq FROM group_memberships WHERE group_id = :id";
        $groupMembersStatement = $this->dbalConnection->prepare($groupMembersSql);
        $groupMembersStatement->bindValue('id', $oldBoardId);
        $groupMembersStatement->execute();

        $results = $groupMembersStatement->fetchAll();
        $this->em->beginTransaction();

        foreach ($results as $result) {
            $member = $this->importBoardMember($result['user_id'], $result['seq'], $newBoardId);
            $this->em->persist($member);
        }

        $this->em->flush();
        $this->em->commit();
        $this->em->clear();
    }

    public function importBoardMember($oldUserId, $seq, $newBoardId)
    {
        $user = $this->ui->importUser($oldUserId);
        $board = $this->em->getReference('VipaJournalBundle:Board', $newBoardId);

        $member = new BoardMember();
        $member->setUser($user)->setBoard($board)->setSeq($seq);

        return $member;
    }
}
