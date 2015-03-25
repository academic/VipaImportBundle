<?php

namespace Okulbilisim\OjsToolsBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Config\Definition\Exception\Exception;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;


use Ojs\UserBundle\Entity\User;

class DataImportUserCommand extends ContainerAwareCommand {

    protected function configure()
    {
        $this
            ->setName('ojs:import:user')
            ->setDescription('Import users');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {

        $kernel = $this->getContainer()->get('kernel');
        $application = new \Symfony\Bundle\FrameworkBundle\Console\Application($kernel);
        $application->setAutoExit(false);
        $output->writeln('<info>Adding  data</info>');

        try{

            $connectionFactory = $this->getContainer()->get('doctrine.dbal.connection_factory');
            $connection = $connectionFactory->createConnection(array(
                'driver' => 'pdo_mysql',
                'user' => 'root',
                'password' => 'root',
                'host' => 'localhost',
                'dbname' => 'dergipark',
            ));

            $users = $connection->fetchAll('SELECT * FROM dergipark.users where disabled=0 limit 1;');

            $users_count = $connection->fetchArray('SELECT count(*) FROM dergipark.users where disabled=0;');

            $i = 1;
            foreach($users as $user){
                print_r($user);

                $em = $this->getContainer()->get("doctrine.orm.entity_manager");

                $entity = new User();
                $entity->setFirstName($user['first_name'] .''. $user['middle_name']);
                $entity->setUsername($user['username']);
                $entity->setLastName($user['last_name']);
                $entity->setEmail($user['email']);
                $em->persist($entity);
                $em->flush();
                $output->writeln('<info>User: '.$i.'/'.$users_count[0].'</info>');
                $i++;

            }

        }catch (Exception $e){
            print_r($e);
        }


    }

}
