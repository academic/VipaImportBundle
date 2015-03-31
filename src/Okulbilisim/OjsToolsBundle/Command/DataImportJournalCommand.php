<?php

namespace Okulbilisim\OjsToolsBundle\Command;

use Okulbilisim\LocationBundle\Entity\Country;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use \Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Input\InputArgument;


use Ojs\UserBundle\Entity\User;
use Ojs\JournalBundle\Entity\Journal;
use Ojs\JournalBundle\Entity\Author;
use Ojs\UserBundle\Entity\UserJournalRole;

class DataImportJournalCommand extends ContainerAwareCommand
{
    protected $roles = [
        'ROLE_ID_SITE_ADMIN' => "0x00000001",
        'ROLE_ID_SUBMITTER' => "0x00000002",
        'ROLE_ID_JOURNAL_MANAGER' => "0x00000010",
        'ROLE_ID_EDITOR' => "0x00000100",
        'ROLE_ID_SECTION_EDITOR' => '0x00000200',
        'ROLE_ID_LAYOUT_EDITOR' => '0x00000300',
        'ROLE_ID_REVIEWER' => "0x00001000",
        'ROLE_ID_COPYEDITOR' => '0x00002000',
        'ROLE_ID_PROOFREADER' => "0x00003000",
        'ROLE_ID_AUTHOR' => "0x00010000",
        'ROLE_ID_READER' => '0x00100000',
        'ROLE_ID_SUBSCRIPTION_MANAGER' => "0x00200000",
    ];
    protected $rolesMap = [
        'ROLE_ID_SITE_ADMIN' => "ROLE_SUPER_ADMIN",
        'ROLE_ID_SUBMITTER' => "ROLE_USER",
        'ROLE_ID_JOURNAL_MANAGER' => "ROLE_JOURNAL_MANAGER",
        'ROLE_ID_EDITOR' => "ROLE_EDITOR",
        'ROLE_ID_SECTION_EDITOR' => 'ROLE_SECTION_EDITOR',
        'ROLE_ID_LAYOUT_EDITOR' => 'ROLE_LAYOUT_EDITOR',
        'ROLE_ID_REVIEWER' => "ROLE_REVIEWER",
        'ROLE_ID_COPYEDITOR' => 'ROLE_COPYEDITOR',
        'ROLE_ID_PROOFREADER' => "ROLE_PROOFREADER",
        'ROLE_ID_AUTHOR' => "ROLE_AUTHOR",
        'ROLE_ID_READER' => 'ROLE_READER',
        'ROLE_ID_SUBSCRIPTION_MANAGER' => "ROLE_SUBSCRIPTION_MANAGER",
    ];

    protected function configure()
    {
        $this
            ->setName('ojs:import:journal')
            ->setDescription('Import journals')
            ->addArgument(
                'JournalId', InputArgument::REQUIRED, 'Journal ID at ');
        $roles = [];
        foreach ($this->roles as $k => $r) {
            $roles[hexdec($r)] = $k;
        }
        $this->roles = $roles;

    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $kernel = $this->getContainer()->get('kernel');
        $application = new \Symfony\Bundle\FrameworkBundle\Console\Application($kernel);
        $application->setAutoExit(false);
        $output->writeln('<info>Adding  data</info>');

        $id = $input->getArgument('JournalId');

        try {

            $connectionFactory = $this->getContainer()->get('doctrine.dbal.connection_factory');
            $connection = $connectionFactory->createConnection(array(
                'driver' => 'pdo_mysql',
                'user' => 'root',
                'password' => '',
                'host' => 'localhost',
                'dbname' => 'dergipark',
            ));


            $em = $this->getContainer()->get("doctrine.orm.entity_manager");


            $journal_raw = $connection->fetchAll('SELECT * FROM journals where journal_id=' . $id . '  limit 1;');


            /*
             * Journal details
             */
            $journal_details = $connection->fetchAll('select locale,setting_name,setting_value from journal_settings where journal_id =' . $id);
            $journal_detail = [];
            foreach ($journal_details as $_journal_detail) {
                if ($_journal_detail['locale'] == 'tr_TR' || empty($_journal_detail['locale']))
                    $journal_detail[$_journal_detail['setting_name']] = $_journal_detail['setting_value'];
            }

            /*
             * Journal Create
             */


            $journal = new Journal();
            isset($journal_detail['title']) && $journal->setTitle($journal_detail['title']);
            isset($journal_detail['abbreviation']) && $journal->setTitleAbbr($journal_detail['abbreviation']);
            isset($journal_detail['description']) && $journal->setDescription($journal_detail['description']);
            isset($journal_detail['homeHeaderTitle']) && $journal->setSubtitle($journal_detail['homeHeaderTitle']);
            isset($journal_detail['printIssn']) && $journal->setIssn($journal_detail['printIssn']);
            isset($journal_detail['onlineIssn']) && $journal->setEissn($journal_detail['onlineIssn']);
            isset($journal_raw['path']) && $journal->setPath($journal_raw['path']);
            // TODO setPeriod
            // $journal->setPeriod();
            isset($journal_raw['path']) && $journal->setSlug($journal_raw['path']);
            $journal->setStatus(1);
            isset($journal_detail['publisherUrl']) && $journal->setUrl($journal_detail['publisherUrl']);
            isset($journal_detail['searchKeywords']) && $journal->setTags($journal_detail['searchKeywords']);
            //$journal->setCountryId();

            $em->persist($journal);
            $em->flush();
            $journal_id = $journal->getId();


            /*
             * Journal users
             */
            $journal_users = $connection->fetchAll('select distinct user_id,role_id from roles where journal_id=' . $id . ' group by user_id order by user_id asc');
            $users_count = $connection->fetchArray('select count(*) from (select distinct user_id from roles where journal_id=' . $id . ' group by user_id order by user_id asc) b;');

            $i = 1;
            foreach ($journal_users as $journal_user) {
                $user = $connection->fetchAll('select * from users where user_id=' . $journal_user['user_id'] . ' limit 1;')[0];

                $usercheck = $em->getRepository('OjsUserBundle:User')->findOneBy(['username' => $user['username']]);
                $user_entity = $usercheck ? $usercheck : new User();
                isset($user['first_name']) && $user_entity->setFirstName($user['first_name']);
                isset($user['middle_name']) && $user_entity->setFirstName($user_entity->getFirstName() . ' ' . $user['middle_name']);
                isset($user['username']) && $user_entity->setUsername($user['username']);
                isset($user['last_name']) && $user_entity->setLastName($user['last_name']);
                isset($user['email']) && $user_entity->setEmail($user['email']);
                $user_entity->generateApiKey();
                isset($user['salutation']) && $user_entity->setTitle($user['salutation']);
                if ($user['disabled'] == 1) {
                    $user_entity->setIsActive(false);
                    $user_entity->setStatus(0);
                }
                $country = $em->getRepository('OkulbilisimLocationBundle:Country')->findOneBy(['iso_code' => $user['country']]);
                if($country instanceof Country)
                    $user_entity->setCountry($country);
                $em->persist($user_entity);
                $em->flush();
                /*
                 * User roles with journal
                 */

                $user_role = new UserJournalRole();
                $user_role->setUser($user_entity);

                $user_role->setJournal($journal);
                $role = $em->getRepository('OjsUserBundle:Role')->findOneBy([
                    'role' => $this->rolesMap[$this->roles[$journal_user['role_id']]]]);
                $user_role->setRole($role);
                $em->persist($user_role);
                $em->flush();


                /*
                 * Add author data
                 */

                $author = new Author();
                $author->setFirstName($user['first_name']);
                $author->setLastName($user['last_name']);
                $author->setMiddleName($user['middle_name']);
                $author->setEmail($user['email']);
                $author->setInitials($user['initials']);
                $author->setTitle($user['salutation']);
                if($country instanceof Country)
                    $author->setCountry($country->getId());
                $author->setUser($user_entity);
                $author->setAddress($user['mailing_address']);
                $em->persist($author);
                $em->flush();


                $output->writeln('<info>User: ' . $i . '/' . $users_count[0] . '</info>');

                $i++;

                /*
                 * Journal Issues
                 */


                /*
                 * Issue Articles
                 */


                /*
                 * Article Files
                 */

            }

            echo "\n=====================\n";

            $output->writeln('<info>Horayy</info>');


        } catch (\Exception $e) {
            echo $e->getMessage();
        }


    }

}