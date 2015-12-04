<?php

namespace OkulBilisim\OjsImportBundle\Importer\PKP;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Faker\Factory;
use FOS\UserBundle\Model\UserManager;
use FOS\UserBundle\Util\TokenGenerator;
use Ojs\UserBundle\Entity\User;
use OkulBilisim\OjsImportBundle\Helper\ImportHelper;
use OkulBilisim\OjsImportBundle\Importer\Importer;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
     * @param Connection $connection
     * @param EntityManager $em
     * @param OutputInterface $consoleOutput
     * @param LoggerInterface $logger
     * @param UserManager $userManager
     * @param TokenGenerator $tokenGenerator
     * @param string $locale
     */
    public function __construct(
        Connection $connection,
        EntityManager $em,
        LoggerInterface $logger,
        OutputInterface $consoleOutput,
        UserManager $userManager,
        TokenGenerator $tokenGenerator,
        $locale
    )
    {
        parent::__construct($connection, $em, $logger, $consoleOutput);
        $this->userManager = $userManager;
        $this->tokenGenerator = $tokenGenerator;
        $this->locale = $locale;
    }

    public function importUser($id, $flush = true)
    {
        $this->consoleOutput->writeln("Reading user #" . $id . "... ", true);

        $sql = "SELECT username, email, disabled FROM users WHERE users.user_id = :id " . ImportHelper::spamUsersFilterSql();
        $statement = $this->connection->prepare($sql);
        $statement->bindValue('id', $id);
        $statement->execute();

        $faker = Factory::create();
        $pkpUser = $statement->fetch();
        $user = null;

        if(!empty($pkpUser['username'])) {
            $user = $this->em
                ->getRepository('OjsUserBundle:User')
                ->findOneBy(['username' => $pkpUser['username']]);
        }

        if (is_null($user)) {
            $user = new User();
            !empty($pkpUser['username']) ?
                $user->setUsername($pkpUser['username']) :
                $user->setUsername($faker->userName);

            !empty($pkpUser['email']) ?
                $user->setEmail($pkpUser['email']) :
                $user->setEmail($faker->companyEmail);

            !empty($pkpUser['disabled']) ?
                $user->setEnabled(!$pkpUser['disabled']) :
                $user->setEnabled(1);

            // Set a random password
            $password = substr($this->tokenGenerator->generateToken(), 0, 8);
            $user->setPlainPassword($password);

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

            if ($flush) {
                $this->em->persist($user);
                $this->em->flush();
            }
        }

        return $user;
    }
}