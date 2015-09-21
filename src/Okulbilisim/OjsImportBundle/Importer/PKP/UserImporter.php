<?php

namespace Okulbilisim\OjsImportBundle\Importer\PKP;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use FOS\UserBundle\Model\UserManager;
use FOS\UserBundle\Util\TokenGenerator;
use Ojs\JournalBundle\Entity\Subject;
use Ojs\UserBundle\Entity\User;

class UserImporter extends Importer
{
    /**
     * @var UserManager
     */
    private $userManager;

    /**
     * @var TokenGenerator
     */
    private $tokenGenerator;

    /**
     * @var string
     */
    private $locale;

    /**
     * UserImporter constructor.
     * @param Connection    $connection
     * @param EntityManager $em
     * @param UserManager $userManager
     * @param TokenGenerator $tokenGenerator
     * @param string $locale
     */
    public function __construct(
        Connection $connection,
        EntityManager $em,
        UserManager $userManager,
        TokenGenerator $tokenGenerator,
        $locale
    )
    {
        parent::__construct($connection, $em);
        $this->userManager = $userManager;
        $this->tokenGenerator = $tokenGenerator;
        $this->locale = $locale;
    }

    public function importUser($id, $flush = true)
    {
        $sql = "SELECT username, email, disabled FROM users WHERE user_id = :id LIMIT 1";
        $statement = $this->connection->prepare($sql);
        $statement->bindValue('id', $id);
        $statement->execute();

        $pkpUser = $statement->fetch();
        $user = null;

        !empty($pkpUser['username']) ?
            $user = $this->em
                ->getRepository('OjsUserBundle:User')
                ->findOneBy(['username' => $pkpUser['username']]) :
            $this->throwInvalidArgumentException('The email is empty');

        if ($user == null) {
            $user = new User();
            $user->setUsername($pkpUser['username']);

            !empty($pkpUser['email']) ?
                $user->setEmail($pkpUser['email']) :
                $this->throwInvalidArgumentException('The email is empty');

            !empty($pkpUser['disabled']) ?
                $user->setEnabled(!$pkpUser['disabled']) :
                $user->setEnabled(1);

            // Set a random password
            $password = substr($this->tokenGenerator->generateToken(), 0, 8);
            $user->setPlainPassword($password);

            $this->importProfile($id, $user);
            $this->importSubjects($id, $user);

            if ($flush) {
                $this->em->persist($user);
                $this->em->flush();
            }
        }

        return $user;
    }

    /**
     * @param   int  $oldId
     * @param   User $user
     * @throws  \Doctrine\DBAL\DBALException
     */
    private function importProfile($oldId, &$user)
    {
        $sql = "SELECT * FROM users WHERE user_id = :id LIMIT 1";
        $statement = $this->connection->prepare($sql);
        $statement->bindValue('id', $oldId);
        $statement->execute();
        $pkpUser = $statement->fetch();

        // Fields which can't be blank
        !empty($pkpUser['first_name']) ? $user->setFirstName($pkpUser['first_name']) : $user->setFirstName('Anonymous');
        !empty($pkpUser['last_name']) ? $user->setLastName($pkpUser['last_name']) : $user->setLastName('Anonymous');

        // Optional fields
        !empty($pkpUser['billing_address']) && $user->setBillingAddress($pkpUser['billing_address']);
        !empty($pkpUser['mailing_address']) && $user->setAddress($pkpUser['mailing_address']);
        !empty($pkpUser['gender']) && $user->setGender($pkpUser['gender']);
        !empty($pkpUser['phone']) && $user->setPhone($pkpUser['phone']);
        !empty($pkpUser['fax']) && $user->setFax($pkpUser['fax']);
        !empty($pkpUser['url']) && $user->setUrl($pkpUser['url']);
    }

    /**
     * @param int $oldId
     * @param User $user
     */
    private function importSubjects($oldId, &$user)
    {
        $sql = "SELECT user_interests.user_id, controlled_vocab_entry_settings.setting_value AS `subject`" .
            " FROM user_interests JOIN controlled_vocab_entry_settings ON user_interests.controlled_vocab_entry_id" .
            " = controlled_vocab_entry_settings.controlled_vocab_entry_id WHERE" .
            " controlled_vocab_entry_settings.setting_name = \"interest\" AND" .
            " user_interests.user_id = :id";

        $statement = $this->connection->prepare($sql);
        $statement->bindValue('id', $oldId);
        $statement->execute();

        $subjects = $statement->fetchAll();

        foreach ($subjects as $pkpSubject) {
            if (!empty($pkpSubject['subject'])) {
                $translation = $this->em
                    ->getRepository('OjsJournalBundle:SubjectTranslation')
                    ->findOneBy(['subject' => $pkpSubject['subject']]);

                if (!$translation) {
                    $subject = new Subject();
                    $subject->setCurrentLocale($this->locale);
                    $subject->setSubject($pkpSubject['subject']);
                } else {
                    $subject = $translation->getTranslatable();
                }

                $user->addSubject($subject);
            }
        }
    }
}