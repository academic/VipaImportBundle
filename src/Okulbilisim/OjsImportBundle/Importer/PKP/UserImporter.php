<?php

namespace Okulbilisim\OjsImportBundle\Importer\PKP;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\ORM\EntityManager;
use FOS\UserBundle\Model\UserManager;
use FOS\UserBundle\Util\TokenGenerator;

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
     * UserImporter constructor.
     * @param Connection $connection
     * @param EntityManager $em
     * @param UserManager $userManager
     * @param TokenGenerator $tokenGenerator
     */
    public function __construct(Connection $connection,
                                EntityManager $em,
                                UserManager $userManager,
                                TokenGenerator $tokenGenerator)
    {
        parent::__construct($connection, $em);
        $this->userManager = $userManager;
        $this->tokenGenerator = $tokenGenerator;
    }

    public function importUser($id)
    {
        $sql = "SELECT username, email, disabled FROM users WHERE user_id = :id LIMIT 1";
        $statement = $this->connection->prepare($sql);
        $statement->bindValue('id', $id);
        $statement->execute();
        $pkpUser = $statement->fetch();

        $user = $this->userManager->createUser();

        isset($pkpUser['username']) ?
            $user->setUsername($pkpUser['username']) :
            $this->throwInvalidArgumentException('The username is empty');

        isset($pkpUser['email']) ?
            $user->setEmail($pkpUser['email']) :
            $this->throwInvalidArgumentException('The email is empty');

        isset($pkpUser['disabled']) ?
            $user->setEnabled(!$pkpUser['disabled']) :
            $user->setEnabled(1);

        // Set a random password
        $password = substr($this->tokenGenerator->generateToken(), 0, 8);
        $user->setPlainPassword($password);

        $this->userManager->updateUser($user);
        $this->importProfile($id, $user->getId());
    }

    private function importProfile($oldId, $newId)
    {
        $sql = "SELECT * FROM users WHERE user_id = :id LIMIT 1";
        $statement = $this->connection->prepare($sql);
        $statement->bindValue('id', $oldId);
        $statement->execute();
        $pkpUser = $statement->fetch();

        $user = $this->em->getRepository('OjsUserBundle:User')->find($newId);

        // Fields which can't be blank
        isset($pkpUser['first_name']) ? $user->setFirstName($pkpUser['first_name']) : $user->setFirstName('Anonymous');
        isset($pkpUser['last_name']) ? $user->setLastName($pkpUser['last_name']) : $user->setLastName('Anonymous');

        // Optional fields
        isset($pkpUser['billing_address']) && $user->setBillingAddress($pkpUser['billing_address']);
        isset($pkpUser['mailing_address']) && $user->setAddress($pkpUser['mailing_address']);
        isset($pkpUser['gender']) && $user->setGender($pkpUser['gender']);
        isset($pkpUser['phone']) && $user->setPhone($pkpUser['phone']);
        isset($pkpUser['fax']) && $user->setFax($pkpUser['fax']);
        isset($pkpUser['url']) && $user->setUrl($pkpUser['url']);

        $this->em->persist($user);
        $this->em->flush();
    }
}