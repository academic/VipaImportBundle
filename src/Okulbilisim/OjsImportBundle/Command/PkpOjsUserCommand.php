<?php

namespace Okulbilisim\OjsImportBundle\Command;

use Doctrine\ORM\EntityManager;
use Okulbilisim\OjsImportBundle\Helper\ImportCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PkpOjsUserCommand extends ImportCommand
{

    protected function configure()
    {
        $this
            ->setName('ojs:import:pkp:user')
            ->setDescription('Import an user from PKP/OJS')
            ->addArgument('id', InputArgument::REQUIRED, 'User ID');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $id = $input->getArgument('id');
        $this->importUser($id);
    }

    private function importUser($id)
    {
        $sql = "SELECT username, email, disabled FROM users WHERE user_id = :id LIMIT 1";
        $statement = $this->connection->prepare($sql);
        $statement->bindValue('id', $id);
        $statement->execute();
        $pkpUser = $statement->fetch();

        $userManager = $this->getContainer()->get('fos_user.user_manager');
        $user = $userManager->createUser();

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
        $tokenGenerator = $this->getContainer()->get('fos_user.util.token_generator');
        $password = substr($tokenGenerator->generateToken(), 0, 8);
        $user->setPlainPassword($password);

        $userManager->updateUser($user);
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
