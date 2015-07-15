<?php

namespace Okulbilisim\OjsToolsBundle\Command;

use Doctrine\DBAL\Connection;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityNotFoundException;
use Gedmo\Translatable\Entity\Repository\TranslationRepository;
use Ojs\Common\Helper\FileHelper;
use Ojs\Common\Params\ArticleFileParams;
use \Ojs\JournalBundle\Document\TransferredRecord;
use Ojs\JournalBundle\Document\WaitingFiles;
use Ojs\JournalBundle\Entity\ArticleAuthor;
use Ojs\JournalBundle\Entity\ArticleFile;
use Ojs\JournalBundle\Entity\ArticleTranslation;
use Ojs\JournalBundle\Entity\Citation;
use Ojs\JournalBundle\Entity\CitationSetting;
use Ojs\JournalBundle\Entity\File;
use Ojs\JournalBundle\Entity\JournalContact;
use Ojs\JournalBundle\Entity\JournalContactTranslation;
use Ojs\JournalBundle\Entity\JournalSection;
use Ojs\JournalBundle\Entity\JournalSectionTranslation;
use Ojs\JournalBundle\Entity\JournalSetting;
use Ojs\JournalBundle\Entity\JournalTranslation;
use Ojs\JournalBundle\Entity\JournalUser;
use Ojs\JournalBundle\Entity\Lang;
use Ojs\JournalBundle\Entity\Subject;
use Ojs\JournalBundle\Entity\SubjectTranslation;
use Ojs\JournalBundle\Entity\SubmissionChecklist;
use Ojs\JournalBundle\Entity\InstitutionTypes;
use Ojs\JournalBundle\Entity\Issue;
use Ojs\JournalBundle\Entity\IssueFile;
use Ojs\SiteBundle\Entity\Block;
use Ojs\SiteBundle\Entity\BlockLink;
use Ojs\UserBundle\Entity\Role;
use Okulbilisim\CmsBundle\Entity\Post;
use Okulbilisim\CmsBundle\Entity\PostTranslation;
use Okulbilisim\OjsToolsBundle\Helper\StringHelper;
use Ojs\JournalBundle\Entity\Article;
use Ojs\JournalBundle\Entity\Institution;
use Ojs\LocationBundle\Entity\Location;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use \Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Input\InputArgument;


use Ojs\UserBundle\Entity\User;
use Ojs\JournalBundle\Entity\Journal;
use Ojs\JournalBundle\Entity\Author;
use Symfony\Component\Finder\Exception\ShellCommandFailureException;

/**
 * Class DataImportJournalCommand
 * ## How to run
 *
 * php app/console ojs:import:journal JournalId MysqlConnectionString
 *
 * JournalId Integer
 * MysqlConnectionString user:password@host/db
 * @package Okulbilisim\OjsToolsBundle\Command
 */
class DataImportJournalCommand extends ContainerAwareCommand
{
    /** @var  string */
    protected $base_domain;
    /**
     * @var array PKPOjs roles data.
     */
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
    /**
     * @var array Ojs roles data map
     */
    protected $rolesMap = [
        'ROLE_ID_SITE_ADMIN' => "ROLE_ADMIN",
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

    protected $institutionTypeMap = [
        0 => 'Other',
        1 => "Tubitak",
        2 => "University",
        3 => "Government",
        4 => "Association",
        5 => "Foundation",
        6 => "Hospital",
        7 => "Chamber",
        8 => "Private"
    ];

    /**
     * @var array PKPOjs database
     */
    protected $database = [
        'driver' => 'pdo_mysql',
        'user' => 'root',
        'password' => 's',
        'host' => 'localhost',
        'dbname' => 'ojs',
    ];

    /** @var  Connection */
    protected $connection;

    /** @var  EntityManager */
    protected $em;

    /** @var  DocumentManager */
    protected $dm;
    /** @var  OutputInterface */
    protected $output;

    /** @var  int */
    protected $journalOldId;

    /** @var  TranslationRepository */
    protected $translationRepository;

    /** @var  array */
    protected $old_journal_details;

    const DEFAULT_INSTITUTION = 1;

    /**
     * Command configuration.
     */
    protected function configure()
    {
        gc_collect_cycles();
        $this
            ->setName('ojs:import:journal')
            ->setDescription('Import journals')
            ->addArgument(
                'JournalId', InputArgument::REQUIRED, 'Journal ID at ')
            ->addArgument(
                'database', InputArgument::REQUIRED, 'PKP Database Connection string [root:123456@localhost/dbname]'
            )
            ->addArgument(
                'base_domain', InputArgument::REQUIRED, "Old pkp/ojs base_domain for image downloads"
            )
        ;
        $roles = [];
        /**
         * we must convert hex data to decimal for database equality.
         */
        foreach ($this->roles as $k => $r) {
            $roles[hexdec($r)] = $k;
        }
        $this->roles = $roles;


    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int|null|void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $time_start = microtime(true);
        $this->parseConnectionString($input->getArgument('database'));

        $this->base_domain = $input->getArgument('base_domain');

        $connectionFactory = $this->getContainer()->get('doctrine.dbal.connection_factory');
        $this->connection = $connectionFactory->createConnection($this->database);
        unset($connectionFactory);

        $this->em = $this->getContainer()->get("doctrine.orm.entity_manager");
        $this->em->getConnection()->getConfiguration()->getSQLLogger(null);

        $this->dm = $this->getContainer()->get('doctrine.odm.mongodb.document_manager');
        $this->translationRepository = $this->em->getRepository('Gedmo\\Translatable\\Entity\\Translation');

        $kernel = $this->getContainer()->get('kernel');
        $application = new Application($kernel);
        $application->setAutoExit(false);
        $this->output = $output;

        // Journal Old ID
        $id = $this->journalOldId = $input->getArgument('JournalId');

        try {

            /**
             * @var array $journal_raw Journal main data.
             * Its contain path
             */
            $journal_raw = $this->connection->fetchAssoc("SELECT path FROM journals where journal_id={$id} limit 1;");

            /**
             * @var array $journal_details Journal detailed data as stored key-value.
             * Its contain `journal_id`,`locale`,`setting_name`,`setting_value`,`setting_type` fields
             */
            $journal_details = $this->connection->fetchAll(" select
                  locale,setting_name,setting_value
                from
                  journal_settings where journal_id = {$id} ");

            if (!$journal_details) {
                $this->output->write("<error>Entity was not found.</error>");
                exit;
            }
            /**
             * I remake array groupped by locale
             */
            $journal_detail = [];
            foreach ($journal_details as $_journal_detail) {
                if ($_journal_detail['locale'] == 'tr_TR' || empty($_journal_detail['locale'])) {
                    $journal_detail['tr_TR'][$_journal_detail['setting_name']] = $_journal_detail['setting_value'];
                } else {
                    $journal_detail[$_journal_detail['locale']][$_journal_detail['setting_name']] = $_journal_detail['setting_value'];
                }
            }

            $this->old_journal_details = $journal_detail;

            unset($_journal_detail, $journal_details);

            /**
             * Journal Create
             */
            $journal_id = $this->createJournal($journal_detail, $journal_raw);
            $this->saveRecordChange($id, $journal_id, 'Ojs\JournalBundle\Entity\Journal');

            $output->writeln("<info>Journal created. #{$journal_id}</info>");

            $this->saveContacts($journal_detail, $journal_raw, $journal_id);
            $output->writeln("\n<info>All contacts saved.</info>");

            $this->connectJournalUsers($journal_id, $output, $id);
            $output->writeln("\nUsers added.");


            $this->createArticles($output, $journal_id, $id);
            $output->writeln("\nArticles added.");

            $time_end = microtime(true);
            $time = $time_end - $time_start;
            $output->writeln("Total $time seconds...");
        } catch (\Exception $e) {
            echo $e->getMessage();
            echo "\n";
            echo $e->getFile();
            echo "\n";
            echo $e->getLine();
            echo "\n";
            echo $e->getTraceAsString();
        }


    }


    /**
     * @param $journal_details
     * @param $journal_raw
     * @return int
     */
    protected function createJournal($journal_details, $journal_raw)
    {
        if (!$journal_details)
            return null;
        $defaultLocale = $this->defaultLocale($journal_details);
        $journal_detail = $journal_details[$defaultLocale];
        unset($journal_details[$defaultLocale]);

        $journal = $this->em->getRepository("OjsJournalBundle:Journal")->findOneBy(['path' => $journal_raw['path']]);
        $journal = $journal ? $journal : new Journal();
        isset($journal_detail['title']) && $journal->setTitle($journal_detail['title']);

        isset($journal_detail['categories']) && $this->setSubjects($journal, $journal_detail['categories']);
        isset($journal_detail['abbreviation']) && $journal->setTitleAbbr($journal_detail['abbreviation']);
        isset($journal_detail['description']) && $journal->setDescription($journal_detail['description']);
        isset($journal_detail['homeHeaderTitle']) && $journal->setSubtitle($journal_detail['homeHeaderTitle']);
        isset($journal_detail['printIssn']) && $journal->setIssn($journal_detail['printIssn']);
        isset($journal_detail['onlineIssn']) && $journal->setEissn($journal_detail['onlineIssn']);
        isset($journal_detail['onlineIssn']) && $journal->setEissn($journal_detail['onlineIssn']);
        isset($journal_raw['path']) && $journal->setPath($journal_raw['path']) && $journal->setSlug($journal_raw['path']);
        $journal->setStatus(3);
        isset($journal_detail['publisherUrl']) && $journal->setUrl($journal_detail['publisherUrl']);
        isset($journal_detail['searchKeywords']) && $journal->setTags($journal_detail['searchKeywords']);
        //$journal->setCountryId();

        //Localized
        foreach ($journal_details as $key => $value) {
            if (isset($value['title'])) {
                $at = $this->em->getRepository("OjsJournalBundle:JournalTranslation")->findOneBy(['field' => 'title', 'locale' => $key, 'object' => $journal]);
                $at = $at ? $at : new JournalTranslation();
                $at->setContent($value['title']);
                $at->setField('title');
                $at->setLocale($key);
                $at->setObject($journal);
                $this->em->persist($at);
                $this->em->flush();
            }
        }


        if (isset($journal_detail['publisherInstitution'])) {
            /**
             * Institution
             */
            /** @var Institution $institution */
            $institution = $this->em->getRepository('OjsJournalBundle:Institution')
                ->findOneBy(['name' => $journal_detail['publisherInstitution']]);
            if ($institution) {
                $journal->setInstitution($institution);
            } else {
                $institution = $this->createInstitution($journal_detail);
                $journal->setInstitution($institution);
            }
        }

        if (isset($journal_detail['supportedSubmissionLocales'])) {

            //Submissio locales
            $submissionLocales = $journal_detail['supportedSubmissionLocales'];
            if ($submissionLocales) {
                $locales = unserialize($submissionLocales);
                foreach ($locales as $locale) {
                    if (empty($locale))
                        continue;
                    $locale = explode('_', $locale)[0];
                    $language = $this->em->getRepository('OjsJournalBundle:Lang')->findOneBy(['code' => $locale]);
                    if (!$language) {
                        $language = new Lang();
                        $language->setCode($locale);
                        $this->em->persist($language);
                    }
                    if (!$journal->getLanguages()->contains($language)) {
                        $journal->addLanguage($language);
                    }
                }
            }

        }
        //update view and download count
        isset($journal_detail['total_views']) && $journal->setViewCount($journal_detail['total_views']);
        isset($journal_detail['total_downloads']) && $journal->setDownloadCount($journal_detail['total_downloads']);
        isset($journal_detail['journalPageFooter']) && $journal->setFooterText($journal_detail['journalPageFooter']);
        $this->em->persist($journal);
        $this->em->flush();

        //submission checklist
        if (in_array('submissionChecklist', $journal_detail)) {
            $checklist = unserialize($journal_detail['submissionChecklist']);

            foreach ($checklist as $item) {
                $locale = explode('_', $defaultLocale)[0];
                if (strlen($item['content']) > 250) {
                    $label = substr($item['content'], 0, 150);
                } else {
                    $label = $item['content'];
                }
                $checkitem = $this->em->getRepository("OjsJournalBundle:SubmissionChecklist")
                    ->findOneBy(['journal' => $journal, 'locale' => $locale, 'visible' => true, 'label' => $label]);
                $checkitem = $checkitem ? $checkitem : new SubmissionChecklist();
                $checkitem->setJournal($journal);
                if (strlen($item['content']) > 250) {
                    $checkitem->setLabel($label)
                        ->setDetail($item['content']);
                } else {
                    $checkitem->setLabel($label);
                }
                $checkitem->setLocale($locale);
                $checkitem->setVisible(true);
                $this->em->persist($checkitem);
                $this->em->flush();
            }
        }


        foreach ($journal_details as $key => $value) {
            if (!in_array('submissionChecklist', $value))
                continue;
            $checklist = unserialize($value['submissionChecklist']);
            foreach ($checklist as $item) {
                $locale = explode('_', $key)[0];
                if (strlen($item['content']) > 250) {
                    $label = substr($item['content'], 0, 150);
                } else {
                    $label = $item['content'];
                }
                $checkitem = $this->em->getRepository("OjsJournalBundle:SubmissionChecklist")
                    ->findOneBy(['journal' => $journal, 'locale' => $locale, 'visible' => true, 'label' => $label]);
                $checkitem = $checkitem ? $checkitem : new SubmissionChecklist();
                $checkitem->setJournal($journal);
                if (strlen($item['content']) > 250) {
                    $checkitem->setLabel($label)
                        ->setDetail($item['content']);
                } else {
                    $checkitem->setLabel($label);
                }
                $checkitem->setLocale($locale);
                $checkitem->setVisible(true);
                $this->em->persist($checkitem);
                $this->em->flush();
            }
        }

        $pages = [];

        if (isset($journal_detail['history'])) {
            $pages['history'] = $this->createPage($journal, $journal_detail['history'], 'History', $defaultLocale);
        }
        if (isset($journal_detail['reviewGuidelines'])) {
            $pages['reviewGuidelines'] = $this->createPage($journal, $journal_detail['reviewGuidelines'], 'Review Guide Lines', $defaultLocale);
        }
        if (isset($journal_detail['additionalHomeContent'])) {
            $pages['additionalHomeContent'] = $this->createPage($journal, $journal_detail['additionalHomeContent'], 'Additional Home Content', $defaultLocale);
        }
        if (isset($journal_detail['reviewPolicy'])) {
            $pages['reviewPolicy'] = $this->createPage($journal, $journal_detail['reviewPolicy'], 'Review Policy', $defaultLocale);
        }
        if (isset($journal_detail['refLinkInstructions'])) {
            $pages['refLinkInstructions'] = $this->createPage($journal, $journal_detail['refLinkInstructions'], 'Ref Link Instructions', $defaultLocale);
        }
        if (isset($journal_detail['readerInformation'])) {
            $pages['readerInformation'] = $this->createPage($journal, $journal_detail['readerInformation'], 'Reader Information', $defaultLocale);
        }
        if (isset($journal_detail['proofInstructions'])) {
            $pages['proofInstructions'] = $this->createPage($journal, $journal_detail['proofInstructions'], 'Proof Instructions', $defaultLocale);
        }
        if (isset($journal_detail['focusScopeDesc'])) {
            $pages['focusScopeDesc'] = $this->createPage($journal, $journal_detail['focusScopeDesc'], 'Focus Scope Desc', $defaultLocale);
        }
        if (isset($journal_detail['copyeditInstructions'])) {
            $pages['copyeditInstructions'] = $this->createPage($journal, $journal_detail['copyeditInstructions'], 'Copy Edit Instructions', $defaultLocale);
        }
        if (isset($journal_detail['authorGuidelines'])) {
            $pages['authorGuidelines'] = $this->createPage($journal, $journal_detail['authorGuidelines'], 'Author Guidelines', $defaultLocale);
        }
        if (isset($journal_detail['authorInformation'])) {
            $pages['authorInformation'] = $this->createPage($journal, $journal_detail['authorInformation'], 'Author Information', $defaultLocale);
        }

        foreach ($journal_details as $key => $value) {
            foreach ($pages as $k => $v) {
                if (isset($value[$k])) {
                    $at = $this->em->getRepository("OkulbilisimCmsBundle:PostTranslation")->findOneBy(['field' => 'content', 'locale' => $key, 'object' => $v]);
                    $at = $at ? $at : new PostTranslation();
                    $at->setContent($value[$k]);
                    $at->setField('content');
                    $at->setLocale($key);
                    $at->setObject($v);
                    $this->em->persist($at);
                    $this->em->flush();
                }
            }
        }

        $this->addPagesToBlock($journal);
        //Journal settings
        foreach ($journal_detail as $key => $value) {
            if (empty($value))
                continue;
            $js = $this->em->getRepository("OjsJournalBundle:JournalSetting")->findOneBy(['setting' => $key, 'value' => $value, 'journal' => $journal]);
            if ($js)
                continue;
            $js = new JournalSetting($key, $value, $journal);
            $this->em->persist($js);
            $this->em->flush();
            $this->output->writeln("<info>Setting: $key Value: $value</info>");
        }

        $journal_id = $journal->getId();
        $this->em->clear();

        unset($journal, $journal_detail, $institution, $journal_raw);

        return $journal_id;
    }

    /**
     * @param int $journal_id
     * @param $output
     * @param $old_journal_id
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    protected function connectJournalUsers($journal_id, $output, $old_journal_id)
    {

        /*
             * Journal users
             */
        $journal_users = $this->connection->fetchAll("select distinct user_id,role_id from roles where journal_id={$old_journal_id} group by user_id order by user_id asc");
        $users_count = $this->connection->fetchArray("select count(*) as c from (select distinct user_id from roles where journal_id={$old_journal_id} group by user_id order by user_id asc) b;");

        foreach ($journal_users as $journal_user) {
            // all relations disconnecting if i use em->clear. I refind journal for fix this issue
            $user = $this->createUser($journal_user);
            if (!$user) {
                continue;
            }
            /*
             * User roles with journal
             */
            $role = $this->addJournalRole($user->getId(), $journal_id, $journal_user['role_id']);

            /*
             * Add author data
             */
            $author = $this->saveAuthorData($user);
            $this->saveRecordChange($journal_user['user_id'], $author->getId(), 'Ojs\JournalBundle\Entity\Author');

            //performance is sucks if i dont use em->clear.
            $this->em->clear();

        }

        unset($user, $userProgress, $journal_user, $journal_users, $old_journal_id);
    }

    /**
     * @param $journal_user
     * @return null|object|User
     */
    protected function createUser($journal_user)
    {
        $user = $this->connection->fetchAll('SELECT * FROM users WHERE user_id=' . $journal_user['user_id'] . ' LIMIT 1;');
        if (!$user)
            return false;
        $user = $user[0];

        $usercheck = $this->em->getRepository('OjsUserBundle:User')->findOneBy(['username' => $user['username']]);
        $user_entity = $usercheck ? $usercheck : new User();
        isset($user['first_name']) && $user_entity->setFirstName($user['first_name']);
        isset($user['middle_name']) && $user_entity->setFirstName($user_entity->getFirstName() . ' ' . $user['middle_name']);
        isset($user['username']) && $user_entity->setUsername($user['username']);
        isset($user['last_name']) && $user_entity->setLastName($user['last_name']);
        isset($user['email']) && $user_entity->setEmail($user['email']);
        isset($user['gender']) && $user_entity->setGender($user['gender']);
        isset($user['initials']) && $user_entity->setInitials($user['initials']);
        isset($user['url']) && $user_entity->setUrl($user['url']);
        isset($user['phone']) && $user_entity->setPhone($user['phone']);
        isset($user['fax']) && $user_entity->setFax($user['fax']);
        isset($user['mailing_address']) && $user_entity->setAddress($user['mailing_address']);
        isset($user['billing_address']) && $user_entity->setBillingAddress($user['billing_address']);
        isset($user['billing_address']) && $user_entity->setBillingAddress($user['billing_address']);
        isset($user['locales']) && $user_entity->setLocales(serialize(explode(':', $user['locales'])));
        $user_entity->generateApiKey();
        isset($user['salutation']) && $user_entity->setTitle($user['salutation']);
        if ($user['disabled'] == 1 && !$usercheck) {
            $user_entity->setIsActive(false);
            $user_entity->setDisableReason(isset($user['disable_reason']) && $user['disable_reason']);
            $user_entity->setStatus(0);
        }
        /*
         $country = $this->em->getRepository('OkulbilisimLocationBundle:Location')->findOneBy(['iso_code' => $user['country']]);
         if ($country instanceof Location)
             $user_entity->setCountry($country); */
        $this->em->persist($user_entity);
        $this->em->flush();
        $this->saveRecordChange($journal_user['user_id'], $user_entity->getId(), 'Ojs\UserBundle\Entity\User');
        $this->output->writeln("<info>User {$user_entity->getUsername()} created. </info>");
        return $user_entity;
    }

    /**
     * @param $user_id
     * @param $journal_id
     * @param $role_id
     * @return bool
     * @throws \Doctrine\ORM\ORMException
     */
    protected function addJournalRole($user_id, $journal_id, $role_id)
    {
        /** @var User $user */
        $user = $this->em->find('OjsUserBundle:User', $user_id);
        /** @var Journal $journal */
        $journal = $this->em->find('OjsJournalBundle:Journal', $journal_id);


        $ojsRole = $this->rolesMap[$this->roles[$role_id]];
        if ($ojsRole === 'ROLE_ADMIN') {
            $user->setAdmin(true);
            $this->em->persist($user);
            $transferred = array('id' => 0, 'name' => 'ROLE_ADMIN');
        } else {
            /** @var Role $role */
            $role = $this->em->getRepository('OjsUserBundle:Role')->findOneBy([
                'role' => $ojsRole]);
            if (!$role) {
                $this->output->writeln("<error>Role not exists. {$role_id}</error>");
                return false;
            }
            $journalUser = $this->em->getRepository('OjsJournalBundle:JournalUser')
                ->findOneBy(
                    array('user' => $user, 'journal' => $journal)
                );
            if(!$journalUser){
                $journalUser = new JournalUser();
            }
            if ($journalUser->getRoles() && $journalUser->getRoles()->contains($role)) {
                return true;
            }
            $journalUser->setUser($user);
            $journalUser->setJournal($journal);
            $journalUser->addRole($role);

            $this->em->persist($journalUser);


            $transferred = array('id' => $role->getId(), 'name' => $role->getName());
        }

        $this->saveRecordChange($role_id, $transferred['id'], 'Ojs\UserBundle\Entity\Role');

        $this->em->flush();
        $this->output->writeln('<info>User ' . $user->getUsername() . ' add as ' . $transferred['name'] . ' to ' . $journal->getTitle() . '</info>');
        unset($user_role, $user, $journal, $role);
        return true;
    }

    /**
     * @param User $user
     * @return Author
     */
    protected function saveAuthorData(User $user)
    {
        $author = new Author();
        $author->setFirstName($user->getFirstName());
        $author->setLastName($user->getLastName());
        //$author->setMiddleName($user['middle_name']);
        $author->setEmail($user->getEmail());
        $author->setInitials($user->getInitials());
        $author->setTitle($user->getTitle());
        $author->setAddress($user->getAddress());
        $author->setBillingAddress($user->getBillingAddress());
        $author->setLocales($user->getLocales());
        $author->setUrl($user->getUrl());
        $author->setPhone($user->getPhone());
        $user->getCountry() !== null && $author->setCountry($user->getCountry());
        $author->setUser($user);
        $this->em->persist($author);
        $this->em->flush();
        $this->output->writeln("<info>User {$user->getUsername()} added as author. </info>");
        return $author;
    }

    /**
     * @param $output
     * @param $journal_id
     * @param $old_journal_id
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    protected function createArticles($output, $journal_id, $old_journal_id)
    {

        $articles = $this->connection->fetchAll("SELECT * FROM articles WHERE journal_id=$old_journal_id");
        if (count($articles) < 1)
            return;

        foreach ($articles as $_article) {
            $this->saveArticleData($_article, $journal_id, false);
        }
        unset($journal, $journal_id, $old_journal_id, $output, $articleProgress, $articles, $_article);
    }

    /**
     * @param array $_article
     * @param Journal $journal
     */
    private function saveArticleData($_article, $journal_id)
    {

        $_article_settings = $this->connection
            ->fetchAll("SELECT setting_name,setting_value,locale FROM article_settings WHERE article_id={$_article['article_id']}");
        $article_settings = [];
        /** groupped locally  */
        foreach ($_article_settings as $as) {
            if ($as['locale'] == '') {
                $article_settings['default'][$as['setting_name']] = $as['setting_value'];
            } else {
                $article_settings[$as['locale']][$as['setting_name']] = $as['setting_value'];
            }
        }


        $journal = $this->em->getRepository("OjsJournalBundle:Journal")->find($journal_id);
        $defaultLocale = $this->defaultLocale($article_settings);

        $wheres = [];

        isset($article_settings[$defaultLocale]['title']) && $wheres['title'] = $article_settings[$defaultLocale]['title'];
        $wheres['journal'] = $journal;
        $article = $this->em->getRepository("OjsJournalBundle:Article")->findOneBy(
            $wheres
        );
        if ($article)
            return $article;

        $article = $article ? $article : new Article();
        $article->setJournal($journal);

        $section = $this->getSection($_article, $journal);
        if ($section instanceof JournalSection) {
            $article->setSection($section);
        }
        isset($article_settings['default']['pub-id::doi']) && $article->setDoi($article_settings['default']['pub-id::doi']);
        isset($article_settings['default']['subject']) && $article->setSubjects($article_settings['default']['subject']);


        switch ($_article['status']) {
            case 0:
                $article->setStatus(0); //waiting
                break;
            case 1:
                //@todo
                $article->setStatus(-2); //unpublished
                break;
            case 3:
                $article->setStatus(3); // published
                break;
            case 4:
                //@todo
                $article->setStatus(-3); //rejected
                break;
        }
        if ($_article['pages']) {
            $pages = explode('-', $_article['pages']);
            isset($pages[0]) && $article->setFirstPage((int)$pages[0] == 0 && !empty($pages[0]) ? (int)StringHelper::roman2int($pages[0]) : (int)$pages[0]);
            isset($pages[1]) && $article->setLastPage((int)$pages[1] == 0 && !empty($pages[1]) ? (int)StringHelper::roman2int($pages[1]) : (int)$pages[1]);

        }

        $username = $this->connection->fetchColumn("SELECT username FROM users WHERE user_id='{$_article['user_id']}'");
        $user = $this->em->getRepository('OjsUserBundle:User')->findOneBy(['username' => $username]);

        if ($user) {
            $article->setSubmitterId($user->getId());
        }

        isset($_article['date_submitted']) && $article->setSubmissionDate(new \DateTime($_article['date_submitted']));
        isset($_article['hide_author']) && $article->setIsAnonymous($_article['hide_author'] ? true : false);
        isset($_article['fileName']) && $article->setHeader($_article['fileName'] ? true : false);

        unset($article_settings['default']);

        if (count($article_settings) < 1) {
            return false;
        }


        $article->setPrimaryLanguage($defaultLocale);

        isset($article_settings[$defaultLocale]['title'])
        && $article->setTitle($article_settings[$defaultLocale]['title']);
        isset($article_settings[$defaultLocale]['abstract'])
        && $article->setAbstract($article_settings[$defaultLocale]['abstract']);
        $this->em->persist($article);

        $article_citations = $this->connection->fetchAll("SELECT raw_citation,citation_id FROM citations WHERE assoc_type=257 AND assoc_id={$_article['article_id']}");
        $i = 1;
        foreach ($article_citations as $ac) {
            if (empty($ac['raw_citation']))
                continue;
            $citation = $this->em->getRepository("OjsJournalBundle:Citation")
                ->findOneBy(['raw' => $ac['raw_citation']]);
            $citation = $citation ? $citation : new Citation();
            $citation->setUpdatedBy('/dev/null');
            $citation->setRaw($ac['raw_citation']);
            $citation->addArticle($article);
            //$citation->setType(); //type not found :\
            $citation->setOrderNum($i);
            $this->em->persist($citation);

            if (!$article->getCitations()->contains($citation))
                $article->addCitation($citation);

            $this->em->persist($article);

            $citationSettingOld = $this->connection->fetchAll("SELECT * FROM citation_settings WHERE citation_id={$ac['citation_id']}");
            foreach ($citationSettingOld as $as) {
                $setting = str_replace('nlm30:', '', $as['setting_name']);
                $citationSetting = $this->em->getRepository("OjsJournalBundle:CitationSetting")
                    ->findOneBy(['citation' => $citation, 'setting' => $setting, 'value' => $as['setting_value']]);
                $citationSetting = $citationSetting ? $citationSetting : new CitationSetting();
                $citationSetting->setCitation($citation);
                $citationSetting->setValue($as['setting_value']);
                $citationSetting->setSetting($setting);
                $citationSetting->setUpdatedBy('/dev/null');
                $this->em->persist($citationSetting);
                $this->em->flush();
                $this->saveRecordChange($ac['citation_id'], $citationSetting->getId(), 'Ojs\JournalBundle\Entity\CitationSetting');

                if (!$citation->getSettings()->contains($citationSetting))
                    $citation->addSetting($citationSetting);
            }
            $this->em->persist($citation);
            $this->em->flush();
            $this->saveRecordChange($ac['citation_id'], $citation->getId(), 'Ojs\JournalBundle\Entity\Citation');

            $i++;
        }
        unset($i);


        unset($article_settings[$defaultLocale]);

        // insert other languages data
        foreach ($article_settings as $locale => $value) {
            $this->output->writeln("<info> $locale locale saving.</info>");
            if (isset($value['title']) && $article->getTitle() != "") {
                $at = $this->em->getRepository('OjsJournalBundle:ArticleTranslation')
                    ->findOneBy(['content' => $value['title'], 'field' => 'title', 'locale' => $locale, 'object' => $article]);
                if (!$at) {
                    $at = new ArticleTranslation();
                    $at->setContent($value['title']);
                    $at->setField('title');
                    $at->setLocale($locale);
                    $at->setObject($article);
                    $this->em->persist($at);
                    $this->em->flush();
                }
            }
            if (isset($value['abstract']) && $article->getAbstract() != "") {
                $at = $this->em->getRepository('OjsJournalBundle:ArticleTranslation')
                    ->findOneBy(['content' => $value['abstract'], 'field' => 'abstract', 'locale' => $locale, 'object' => $article]);
                if (!$at) {
                    $at = new ArticleTranslation();
                    $at->setContent($value['abstract']);
                    $at->setField('abstract');
                    $at->setLocale($locale);
                    $at->setObject($article);
                    $this->em->persist($at);
                    $this->em->flush();
                }
            }
            if (isset($value['subject']) && $article->getSubjects() != "") {
                $at = $this->em->getRepository('OjsJournalBundle:ArticleTranslation')
                    ->findOneBy(['content' => $value['subject'], 'field' => 'subjects', 'locale' => $locale, 'object' => $article]);
                if (!$at) {
                    $at = new ArticleTranslation();
                    $at->setContent($value['subject']);
                    $at->setField('subjects');
                    $at->setLocale($locale);
                    $at->setObject($article);
                    $this->em->persist($at);
                    $this->em->flush();
                }
            }

            $this->em->persist($article);
            $this->em->flush();
        }


        $this->em->persist($article);

        $this->em->flush();
        /** Article files */
        $this->saveArticleFiles($article->getId(), $_article['article_id']);

        $published_article = $this->connection->fetchAssoc("SELECT issue_id FROM published_articles WHERE article_id={$_article['article_id']}");
        if ($published_article) {
            //have an issue
            $issue = $this->connection->fetchAssoc("SELECT * FROM issues WHERE issue_id={$published_article['issue_id']}");
            if ($issue) {
                $issue = $this->saveIssue($issue, $journal_id, $article);
                if ($section instanceof JournalSection && !$issue->getSections()->contains($section))
                    $issue->addSection($section);
                $this->em->persist($issue);
                $this->saveRecordChange($published_article['issue_id'], $issue->getId(), 'Ojs\JournalBundle\Entity\Issue');
                $article->setIssue($issue);
            }
        }

        $this->em->persist($article);
        $this->em->flush();
        $this->output->writeln("<info>Article {$article->getTitle()} created.</info>");
        $this->saveRecordChange($_article['article_id'], $article->getId(), 'Ojs\JournalBundle\Entity\Article');

        $this->em->clear();
        unset($article, $published_article, $article_settings, $article_citations,
            $_article, $_article_settings, $citation, $citationSetting, $citationSettingOld
            , $defaultLocale, $pages, $user, $username);
    }


    /**
     * @param int $article_id
     * @param int $old_article_id
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     */
    public function saveArticleFiles($article_id, $old_article_id)
    {
        /** @var Article $article */
        $article = $this->em->find('OjsJournalBundle:Article', $article_id);
        $filehelper = new FileHelper();
        $journal_path = $article->getJournal()->getPath();

        $article_galleys = $this->connection->fetchAll("SELECT ag.article_id,ag.galley_id, ag.file_id,ag.label,ag.locale FROM article_galleys ag WHERE ag.article_id={$old_article_id}");
        if (count($article_galleys) < 1) {
            $this->output->writeln("<error>{$old_article_id} id'li article için dosya bulunamadı.</error>");
        }
        foreach ($article_galleys as $galley) {
            if (!$article)
                $article = $this->em->find('OjsJournalBundle:Article', $article->getId());
            $article_file = $this->connection->fetchAll("SELECT f.file_name,f.file_type,f.file_size,f.source_revision FROM article_files f WHERE f.file_id={$galley['file_id']}");
            if ($article_file) {
                $article_file = $article_file[0];
            }
            if (!$article_file) {
                $this->output->writeln("<error>{$galley['file_id']} id'li dosya bulunamadı.</error>");
                continue;
            }
            if (isset($this->old_journal_details['tr_TR']) && isset($this->old_journal_details['tr_TR']['enablePublicGalleyId']) &&
                $this->old_journal_details['tr_TR']['enablePublicGalleyId'] == 1
            ) {
                $url = "{$this->base_domain}/$journal_path/article/download/{$galley['article_id']}/{$galley['galley_id']}";
            } else {
                $galley_setting = $this->connection->fetchAssoc("SELECT setting_value FROM article_galley_settings WHERE galley_id={$galley['galley_id']} and setting_name='pub-id::publisher-id'");
                if (!$galley_setting['setting_value']) {
                    $this->output->writeln("<error>{$galley['galley_id']} id'li galley için publisher-id bulunamadı.</error>");
                    $this->output->writeln("<comment>{$galley['galley_id']} id'li galley için galley verisiyle devam ediliyor.</comment>");
                    $url = "{$this->base_domain}/$journal_path/article/download/{$galley['article_id']}/{$galley['galley_id']}";
                } else {
                    $url = "{$this->base_domain}/$journal_path/article/download/{$galley['article_id']}/{$galley_setting['setting_value']}";
                }
            }
            $file = new File();
            $file->setName($article_file['file_name']);
            $file->setMimeType($article_file['file_type']);
            $file->setSize($article_file['file_size']);
            $version = $article_file['source_revision'];
            $this->em->persist($file);

            $article_file = new ArticleFile();
            $article_file->setTitle($galley['label']);
            $article_file->setLangCode($galley['locale']);
            $article_file->setFile($file);
            $article_file->setArticle($article);
            $article_file->setType(0);
            $article_file->setVersion($version ? $version : 1);

            $this->em->persist($article_file);
            $this->em->flush();
            $this->saveRecordChange($galley['file_id'], $file->getId(), 'Ojs\JournalBundle\Entity\File');

            $waitingfile = new WaitingFiles();
            $filepath = "uploads/articlefiles/" . $filehelper->generatePath($article_file->getFile()->getName()) . $article_file->getFile()->getName();
            $waitingfile->setPath($filepath)
                ->setUrl($url)
                ->setOldId($galley['file_id'])
                ->setNewId($file->getId());
            $this->dm->persist($waitingfile);
            $this->dm->flush();
            $this->output->writeln("<question>FileId: {$file->getId()} \n FileUrl: $url \n FilePath: $filepath</question>");


        }

        $article_supplementary_files = $this->connection->fetchAll("SELECT asp.article_id, asp.file_id,asp.supp_id,asp.type FROM article_supplementary_files asp WHERE asp.article_id={$old_article_id}");

        if (count($article_supplementary_files) < 1) {
            $this->output->writeln("<error>{$old_article_id} id'li article için ek dosya bulunamadı.</error>");
        }

        foreach ($article_supplementary_files as $sup_file) {
            if (!isset($sup_file['supp_id'])) {
                continue;
            }
            $sup_settings = $this->connection->fetchAll("SELECT s.setting_name,s.setting_value,s.locale FROM article_supp_file_settings s WHERE s.supp_id={$sup_file['supp_id']}");
            $sup_file_detail = $this->connection->fetchAssoc("SELECT f.file_type,f.file_size,f.file_name,f.source_revision FROM article_files f WHERE f.file_id={$sup_file['file_id']}");

            $supp_settings = [];
            /** groupped locally  */
            foreach ($sup_settings as $as) {
                if ($as['locale'] == '') {
                    $supp_settings['default'][$as['setting_name']] = $as['setting_value'];
                } else {
                    $supp_settings[$as['locale']][$as['setting_name']] = $as['setting_value'];
                }
            }
            if (count($sup_settings) > 1) {
                $defaultLocale = $this->defaultLocale($supp_settings);
            } else {
                $defaultLocale = 'default';
            }


            $file = new File();
            $file->setName(strtolower($sup_file_detail['file_name']));
            $file->setMimeType($sup_file_detail['file_type']);
            $file->setSize($sup_file_detail['file_size']);
            $version = $sup_file_detail['source_revision'];
            $this->em->persist($file);


            $article_file = new ArticleFile();
            isset($supp_settings[$defaultLocale]) && isset($supp_settings[$defaultLocale]['title']) && $article_file->setTitle($supp_settings[$defaultLocale]['title']);
            $article_file->setLangCode($defaultLocale);
            $article_file->setFile($file);
            $article_file->setArticle($article);
            $article_file->setType($this->supplementary_files($sup_file['type']));
            $article_file->setVersion($version ? $version : 1);
            isset($supp_settings[$defaultLocale]) && isset($supp_settings[$defaultLocale]['subject']) && $article_file->setKeywords($supp_settings[$defaultLocale]['subject']);
            $this->em->persist($article_file);

            $this->em->flush();
            if (isset($sup_file['supp_id'])) {
                $url = "{$this->base_domain}/{$journal_path}/article/downloadSuppFile/{$sup_file['article_id']}/{$sup_file['supp_id']}";
                $waitingfile = new WaitingFiles();
                $filepath = "uploads/articlefiles/" . $filehelper->generatePath($article_file->getFile()->getName()) . $article_file->getFile()->getName();
                $waitingfile->setPath($filepath)
                    ->setUrl($url)
                    ->setOldId($sup_file['supp_id'])
                    ->setNewId($file->getId());
                $this->dm->persist($waitingfile);
                $this->dm->flush();
                $this->output->writeln("<question>FileId: {$file->getId()} \n FileUrl: $url \n FilePath: $filepath</question>");
            }
        }

        //Article authors
        $authors = $this->connection->fetchAll("SELECT * FROM authors as a WHERE submission_id=$old_article_id");
        $a = 2;
        foreach ($authors as $author) {
            $newAuthor = new Author();
            $newAuthor->setFirstName($author['first_name'])
                ->setMiddleName($author['middle_name'])
                ->setLastName($author['last_name'])
                ->setTitle($author['suffix'])
                ->setEmail($author['email'])
                ->setUrl($author['url']);
            $this->em->persist($newAuthor);
            $articleAuthor = new ArticleAuthor();
            $articleAuthor->setArticle($article)
                ->setAuthor($newAuthor)
                ->setAuthorOrder($author['primary_contact'] == 1 ? 1 : $a++);
            $this->em->persist($articleAuthor);

            $newAuthor->addArticleAuthor($articleAuthor);
            $this->em->persist($newAuthor);

            $article->addArticleAuthor($articleAuthor);
            $this->em->persist($article);
            $this->output->writeln("<comment>Author {$newAuthor->getFirstName()} {$newAuthor->getLastName()} added to article.</comment>");
        }

        $this->em->flush();


        unset($article, $defaultLocale, $article_file, $article_galleys, $article_id, $article_supplementary_files, $defaultLocale
            , $article_id, $as, $file, $galley, $old_article_id, $sup_file, $sup_settings, $sup_file_detail, $supp_settings, $article_supplementary_files);
    }

    /**
     * @param $type
     * @return bool
     */
    protected function supplementary_files($type)
    {
        $typeMap = [
            'Ara?t?rma arac?' => ArticleFileParams::SUPPLEMENTARY_FILE,
            'Araştırma aracı' => ArticleFileParams::SUPPLEMENTARY_FILE,
            'Araştırma araçları' => ArticleFileParams::SUPPLEMENTARY_FILE,
            'Araştırma Enstürmanları' => ArticleFileParams::SUPPLEMENTARY_FILE,
            'Araştırma enstürmanları' => ArticleFileParams::SUPPLEMENTARY_FILE,
            'Araştırma Materyalleri' => ArticleFileParams::RESEARCH_METARIALS,
            'Araştırma sonuçları' => ArticleFileParams::RESEARCH_RESULTS,
            'Data Analysis' => ArticleFileParams::DATA_ANALYSIS,
            'Data Set' => ArticleFileParams::DATA_SET,
            'Kaynak metin' => ArticleFileParams::SOURCE_TEXT,
            'Kopya / Suret' => ArticleFileParams::SUPPLEMENTARY_FILE,
            'Research Instrument' => ArticleFileParams::SUPPLEMENTARY_FILE,
            'Research Materials' => ArticleFileParams::RESEARCH_METARIALS,
            'Research Results' => ArticleFileParams::RESEARCH_RESULTS,
            'Source Text' => ArticleFileParams::FULL_TEXT,
            'Suretler' => ArticleFileParams::SUPPLEMENTARY_FILE,
            'Transcripts' => ArticleFileParams::TRANSCRIPTS,
            'Veri analizi' => ArticleFileParams::SUPPLEMENTARY_FILE,
            'Veri Seti' => ArticleFileParams::SUPPLEMENTARY_FILE,
            'Veri takımı' => ArticleFileParams::SUPPLEMENTARY_FILE,
        ];
        if (!$type)
            return false;
        if (!isset($typeMap[$type]))
            return false;
        return $typeMap[$type];
    }

    /**
     * @param $data
     * @return bool|\Doctrine\Common\Proxy\Proxy|object|Institution
     * @throws \Doctrine\ORM\ORMException
     */
    protected function createInstitution($data)
    {
        if (!isset($data['publisherInstitution']) || empty($data['publisherInstitution'])) {
            return $this->em->find("OjsJournalBundle:Institution", 1);
        }
        $institution = new Institution();
        $institution->setName($data['publisherInstitution']);
        $institution->setUrl($data['publisherUrl']);
        $institutionType = '';
        isset($data['publisherType']) && $institutionType = $this->getInstitutionType($data['publisherType']);
        if ($institutionType)
            $institution->setInstitutionType($institutionType);

        $this->em->persist($institution);
        $this->em->flush();
        $this->output->writeln("<info>Institution created #{$institution->getId()} as name {$institution->getName()}</info>");
        return $institution;
    }

    /**
     * @param $type_id
     * @return null|InstitutionTypes
     */
    protected function getInstitutionType($type_id)
    {
        $type = null;
        isset($this->institutionTypeMap[$type_id]) && $typeText = $this->institutionTypeMap[$type_id];
        isset($typeText) && $type = $this->em->getRepository("OjsJournalBundle:InstitutionTypes")->findOneBy(['name' => $typeText]);
        return $type;
    }

    /**
     * @param array $issueData
     * @param int $journal_id
     * @param int $article
     * @return Issue
     */
    protected function saveIssue(array $issueData, $journal_id, $article_id)
    {
        $article = $this->em->find("OjsJournalBundle:Article", $article_id);
        $issue_settings_ = $this->connection->fetchAll("SELECT locale,setting_value,setting_name FROM issue_settings WHERE issue_id={$issueData['issue_id']}");
        $issue_settings = [];
        /** groupped locally  */
        foreach ($issue_settings_ as $as) {
            if ($as['locale'] == '') {
                $issue_settings['default'][$as['setting_name']] = $as['setting_value'];
            } else {
                $issue_settings[$as['locale']][$as['setting_name']] = $as['setting_value'];
            }
        }
        if (count($issue_settings) > 0) {
            $defaultLocale = $this->defaultLocale($issue_settings);
        } else {
            $issue_settings['default'] = [
                'title' => '',
                'description' => '',
                'fileName' => ''
            ];
            $defaultLocale = 'default';
        }
        $checkIssue = $this->em->getRepository("OjsJournalBundle:Issue")->findOneBy([
            'number' => $issueData['number'],
            'year' => $issueData['year'],
            'volume' => $issueData['volume'],
            'title' => $issue_settings[$defaultLocale]['title'],
            'journalId' => $journal_id
        ]);
        if ($checkIssue) {
            $checkIssue->addArticle($article);
            $this->em->persist($checkIssue);
            return $checkIssue;
        }

        $issue = new Issue();
        isset($issue_settings[$defaultLocale]['title']) && $issue->setTitle($issue_settings[$defaultLocale]['title']);
        isset($issue_settings[$defaultLocale]['description']) && $issue->setDescription($issue_settings[$defaultLocale]['description']);
        $issue->setJournalId($journal_id);
        $issue->setJournal($this->em->find("OjsJournalBundle:Journal", $journal_id));
        isset($issueData['date_published']) && $issue->setDatePublished((new \DateTime($issueData['date_published'])));
        $issue->setVolume($issueData['volume']);
        $issue->setYear($issueData['year']);
        $issue->setSpecial(0);
        $issue->setNumber($issueData['number']);
        if (isset($issue_settings[$defaultLocale]['fileName'])) {
            $journal = $issue->getJournal();
            $issue->setCover($issue_settings[$defaultLocale]['fileName']);
            $journal->setImage($issue_settings[$defaultLocale]['fileName']);
            $this->em->persist($journal);
            $fileUrl = "{$this->base_domain}/public/journals/{$this->journalOldId}/{$issue_settings[$defaultLocale]['fileName']}";
            $waitingfile = new WaitingFiles();
            $fileHelper = new FileHelper();

            $filepath = "uploads/journalfiles/" . $fileHelper->generatePath($issue_settings[$defaultLocale]['fileName']) . $issue_settings[$defaultLocale]['fileName'];
            $waitingfile->setPath($filepath)
                ->setUrl($fileUrl)
                ->setOldId($issueData['issue_id']);
            $this->dm->persist($waitingfile);
            $this->dm->flush();
            $this->output->writeln("<comment>Saved issue cover image #{$waitingfile->getPath()} to waiting files</comment>");
        }
        $this->em->persist($issue);
        $this->em->flush();

        $this->saveIssueFiles($issue, $issueData['issue_id']);
        $issue->addArticle($article);
        $this->em->flush();
        $this->output->writeln("<info>Issue {$issue->getTitle()} created and added to {$article->getTitle()}");
        return $issue;
    }

    protected function saveIssueFiles(Issue $issue, $old_issue_id)
    {
        $galleys = $this->connection->fetchAll("SELECT * FROM issue_galleys WHERE issue_id={$old_issue_id}");
        $journalPath = $issue->getJournal()->getPath();
        $fileHelper = new FileHelper();
        foreach ($galleys as $galley) {

            $oldFile = $this->connection->fetchAssoc("SELECT * FROM issue_files WHERE file_id={$galley['file_id']}");
            $file = new File();
            $file->setName($oldFile['file_name'])
                ->setMimeType($oldFile['file_type'])
                ->setSize($oldFile['file_size']);

            //@todo continue after issuefile feature
            $issueFile = new IssueFile();
            $issueFile->setFile($file)
                ->setIssue($issue)
                ->setTitle($oldFile['file_name']);

            $fileUrl = "{$this->base_domain}/$journalPath/issue/download/{$galley['issue_id']}/{$galley['galley_id']}";
            $waitingfile = new WaitingFiles();
            $filepath = "uploads/issuefiles/" . $fileHelper->generatePath($issueFile->getFile()->getName()) . $issueFile->getFile()->getName();
            $waitingfile->setPath($filepath)
                ->setUrl($fileUrl)
                ->setOldId($galley['file_id'])
                ->setNewId($file->getId());
            $this->dm->persist($waitingfile);
            $this->dm->flush();
        }

        // where is issue files ?
        return true;
    }

    /**
     * Mapping array as group by language
     * @param $data
     * @return mixed
     */
    public function defaultLocale($data)
    {
        if (count($data) < 1)
            return 'default';
        //find primary languages
        $sizeof = array_map(function ($a) {
            return count($a);
        }, $data);
        return array_search(max($sizeof), $sizeof);
    }

    /**
     * @param int $old_id
     * @param int $new_id
     * @param string $entity
     */
    protected function saveRecordChange($old_id, $new_id, $entity)
    {
        $changeRecordJournal = new TransferredRecord();
        $changeRecordJournal
            ->setOldId($old_id)
            ->setNewId($new_id)
            ->setEntity($entity);
        $this->dm->persist($changeRecordJournal);
        $this->dm->flush();
        $this->dm->clear();
    }

    /**
     * @param $journal_detail
     * @param $journal_raw
     * @param $journal_id
     * @throws \Doctrine\ORM\ORMException
     * @throws \Doctrine\ORM\OptimisticLockException
     * @throws \Doctrine\ORM\TransactionRequiredException
     * @throws \Exception
     */
    protected function saveContacts($journal_detail, $journal_raw, $journal_id)
    {
        /** @var Journal $journal */
        $journal = $this->em->find('OjsJournalBundle:Journal', $journal_id);
        // save default contact
        $defaultLocale = $this->defaultLocale($journal_detail);

        if (isset($journal_detail[$defaultLocale]['contactAffiliation'])) {
            $contactType = $this->em->getRepository("OjsJournalBundle:ContactTypes")->findOneBy(['name' => 'Journal Contact']);
            $where = [];
            $where['affiliation'] = $journal_detail[$defaultLocale]['contactAffiliation'];
            isset($journal_detail[$defaultLocale]['contactEmail']) && $where['email'] = $journal_detail[$defaultLocale]['contactEmail'];
            isset($journal_detail[$defaultLocale]['contactFax']) && $where['fax'] = $journal_detail[$defaultLocale]['contactFax'];
            isset($journal_detail[$defaultLocale]['contactMailingAddress']) && $where['address'] = $journal_detail[$defaultLocale]['contactMailingAddress'];
            if (isset($journal_detail[$defaultLocale]['contactName'])) {
                $name = explode(' ', $journal_detail[$defaultLocale]['contactName']);
                $firstName = $name[0];
                unset($name[0]);
                $lastName = join(' ', $name);
                $where['firstName'] = $firstName;
                $where['lastName'] = $lastName;
            }
            isset($journal_detail[$defaultLocale]['contactPhone']) && $where['phone'] = $journal_detail[$defaultLocale]['contactPhone'];
            isset($journal_detail[$defaultLocale]['contactTitle']) && $where['title'] = $journal_detail[$defaultLocale]['contactTitle'];
            $where['contactType'] = $contactType;
            $where['journal'] = $journal;


            $contact = $this->em->getRepository("OjsJournalBundle:JournalContact")->findOneBy($where);
            $contact = $contact ? $contact : new JournalContact();
            $contact->setAffiliation($journal_detail[$defaultLocale]['contactAffiliation']);

            isset($journal_detail[$defaultLocale]['contactEmail']) && $contact->setEmail($journal_detail[$defaultLocale]['contactEmail']);
            isset($journal_detail[$defaultLocale]['contactFax']) && $contact->setFax($journal_detail[$defaultLocale]['contactFax']);
            isset($journal_detail[$defaultLocale]['contactMailingAddress']) && $contact->setAddress($journal_detail[$defaultLocale]['contactMailingAddress']);
            if (isset($journal_detail[$defaultLocale]['contactName'])) {
                $name = explode(' ', $journal_detail[$defaultLocale]['contactName']);
                $firstName = $name[0];
                unset($name[0]);
                $lastName = join(' ', $name);
                $contact->setFirstName($firstName)
                    ->setLastName($lastName);
            }
            isset($journal_detail[$defaultLocale]['contactPhone']) && $contact->setPhone($journal_detail[$defaultLocale]['contactPhone']);
            isset($journal_detail[$defaultLocale]['contactTitle']) && $contact->setTitle($journal_detail[$defaultLocale]['contactTitle']);

            if (!$contactType) {
                throw new \Exception("You must import default contact types.");
            }
            $contact->setContactType($contactType);
            $contact->setJournal($journal);
            $this->em->persist($contact);
            foreach ($journal_detail as $key => $value) {
                if ($key == $defaultLocale)
                    continue;
                if (isset($value['contactAffiliation'])) {
                    $aft = new JournalContactTranslation();
                    $aft->setContent($value['contactAffiliation'])
                        ->setField('affiliation')
                        ->setLocale($key)
                        ->setObject($contact);
                    $this->em->persist($aft);
                }
                if (isset($value['contactMailingAddress'])) {
                    $adt = new JournalContactTranslation();
                    $adt->setContent($value['contactMailingAddress'])
                        ->setField('address')
                        ->setObject($contact)
                        ->setLocale($key);
                    $this->em->persist($adt);
                }
            }


            $this->em->flush();
            $this->output->writeln("<info>Contact {$contact->getAffiliation()} {$contact->getTitle()} created. </info>");
        };
        if (isset($journal_detail[$defaultLocale]['supportName'])) {
            $contactType = $this->em->getRepository("OjsJournalBundle:ContactTypes")->findOneBy(['name' => 'Submission Support']);

            $contact = $this->em->getRepository("OjsJournalBundle:JournalContact")->findOneBy(
                [
                    'affiliation' => $journal_detail[$defaultLocale]['supportName'],
                    'email' => $journal_detail[$defaultLocale]['supportEmail'],
                    'phone' => $journal_detail[$defaultLocale]['supportPhone'],
                    'firstName' => $journal_detail[$defaultLocale]['supportName'],
                    'contactType' => $contactType,
                    'journal' => $journal
                ]
            );
            $contact = $contact ? $contact : new JournalContact();
            $contact->setAffiliation($journal_detail[$defaultLocale]['supportName']);
            $contact->setEmail($journal_detail[$defaultLocale]['supportEmail']);
            $contact->setPhone($journal_detail[$defaultLocale]['supportPhone']);
            $contact->setFirstName($journal_detail[$defaultLocale]['supportName']);
            if (!$contactType) {
                throw new \Exception("You must import default contact types.");
            }

            $contact->setContactType($contactType);
            $contact->setJournal($journal);
            $this->em->persist($contact);

            foreach ($journal_detail as $key => $value) {
                if ($key == $defaultLocale || !isset($value['supportName']))
                    continue;
                $aft = new JournalContactTranslation();
                $aft->setContent($value['supportName'])
                    ->setField('affiliation')
                    ->setLocale($key)
                    ->setObject($contact);
                $this->em->persist($aft);
            }
            $this->em->flush();
            $this->output->writeln("<info>Contact {$contact->getAffiliation()} {$contact->getTitle()} created. </info>");

        }

    }

    /**
     * @param $article_data
     * @param Journal $journal
     * @return JournalSection
     */
    public function getSection($article_data, Journal $journal)
    {
        $section_id = $article_data['section_id'];
        $sections = $this->connection->fetchAll("SELECT * FROM section_settings WHERE section_id={$section_id}");
        if (!$sections)
            return false;
        $section_detail = $this->connection->fetchAssoc("SELECT * FROM sections WHERE section_id={$section_id}");
        if (!$section_detail)
            return false;
        $section_settings = [];
        /** groupped locally  */
        foreach ($sections as $as) {
            if ($as['locale'] == '' or $as['locale'] == 'tr_TR') {
                $section_settings['default'][$as['setting_name']] = $as['setting_value'];
            } else {
                $section_settings[$as['locale']][$as['setting_name']] = $as['setting_value'];
            }
        }
        if (count($section_settings) > 1) {
            $defaultLocale = $this->defaultLocale($section_settings);
        } else {
            $defaultLocale = 'default';
        }
        if (!isset($section_settings[$defaultLocale]))
            return false;
        $check = $this->em->getRepository('OjsJournalBundle:JournalSection')->findOneBy(['journalId' => $journal->getId(), 'title' => $section_settings[$defaultLocale]['title']]);
        if ($check) {
            return $check;
        }
        $newSection = new JournalSection();
        $newSection->setJournal($journal);
        $newSection->setTitle($section_settings[$defaultLocale]['title']);
        isset($section_detail['hide_title']) && $newSection->setHideTitle($section_detail['hide_title']);
        $this->em->persist($newSection);
        unset($section_settings[$defaultLocale]);
        foreach ($section_settings as $key => $section) {
            if (isset($section['title'])) {
                $at = new JournalSectionTranslation();
                $at->setContent($section['title']);
                $at->setField('title');
                $at->setLocale($key);
                $at->setObject($newSection);
                $this->em->persist($at);
                $this->em->flush();
            }

        }
        $this->em->persist($newSection);
        $this->em->flush();
        $this->output->writeln("<info>Section {$newSection->getTitle()} created.</info>");
        return $newSection;
    }

    public function setSubjects(Journal &$journal, $categories)
    {
        if ($categories == "N;" || empty($categories)) {
            return null;
        }

        $categories = unserialize($categories);
        if (empty($categories)) {
            return null;
        }
        $_categories = [];
        foreach ($categories as $category) {
            $data = $this->connection->fetchAll("SELECT * FROM controlled_vocab_entry_settings WHERE controlled_vocab_entry_id='{$category}'");
            $cat = [];
            foreach ($data as $d) {
                $cat[$d['locale']] = $d['setting_value'];
            }
            $_categories[] = $cat;

        }
        $this->em->persist($journal);
        foreach ($_categories as $category) {
            /** @var Subject $subject */
            $subject = $this->em->getRepository('OjsJournalBundle:Subject')->findOneBy(['subject' => $category['tr_TR']]);
            if (!$subject) {
                continue;
                $subject = new Subject();
                $subject->setSubject($category['tr_TR']);
                $this->em->persist($subject);
                if (isset($category['en_US'])) {
                    $at = new SubjectTranslation();
                    $at->setContent($category['en_US']);
                    $at->setField('subject');
                    $at->setLocale('en_US');
                    $at->setObject($subject);
                    $this->em->persist($at);
                    $this->em->flush();
                }
            }
            if (!$journal->getSubjects()->contains($subject))
                $journal->addSubject($subject);
            if ($subject->getJournals()->contains($journal))
                $subject->addJournal($journal);
            $this->em->persist($journal);
            $this->em->persist($subject);

        }
        $this->em->flush();

    }

    /**
     * @param Journal $journal
     * @param $content
     * @param $title
     * @param $locale
     */
    public function createPage(Journal $journal, $content, $title, $locale)
    {
        $twig = $this->getContainer()->get('okulbilisimcmsbundle.twig.post_extension');
        $journalKey = $twig->encode($journal);
        $page = $this->em->getRepository('OkulbilisimCmsBundle:Post')
            ->findOneBy(['title' => $title, 'object' => $journalKey, 'objectId' => $journal->getId()
                , 'locale' => $locale, 'post_type' => 'default', 'unique_key' => $journalKey . $journal->getId()]);
        if ($page)
            return $page;
        $page = new Post();
        $page->setTitle($title)
            ->setContent($content)
            ->setObject($journalKey)
            ->setObjectId($journal->getId())
            ->setLocale($locale)
            ->setPostType('default')
            ->setStatus(1)
            ->setUniqueKey($journalKey . $journal->getId());
        $this->em->persist($page);
        $this->em->flush();
        $this->output->writeln("<info>Page created #{$page->getId()} as name {$page->getTitle()}</info>");
        return $page;
    }

    /**
     * @param Journal $journal
     */
    public function addPagesToBlock(Journal $journal)
    {
        if (!$journal->getSlug())
            return;
        $twig = $this->getContainer()->get('okulbilisimcmsbundle.twig.post_extension');
        $journalKey = $twig->encode($journal);
        $pages = $this->em->getRepository('OkulbilisimCmsBundle:Post')->findBy([
            'object' => $journalKey,
            'objectId' => $journal->getId()
        ]);
        if (!$pages)
            return null;

        $block = $this->em->getRepository('OjsSiteBundle:Block')->findOneBy(['objectType' => 'journal', 'objectId' => $journal->getId(), 'type' => 'link']);
        if (!$block) {
            $block = new Block();
            $block->setObjectType('journal')
                ->setObjectId($journal->getId())
                ->setType('link')
                ->setColor('primary')
                ->setTitle("Sayfalar");
            $this->em->persist($block);
            $this->em->flush();
        }
        $router = $this->getContainer()->get('router');
        foreach ($pages as $page) {
            $url = "http:" . $router->generate('ojs_journal_index_page_detail', ['institution' => $journal->getInstitution()->getSlug(), 'journal_slug' => $journal->getSlug(), 'slug' => $page->getSlug()]);
            $blockLink = $this->em->getRepository("OjsSiteBundle:BlockLink")->findOneBy(['block' => $block, 'post' => $page, 'url' => $url]);
            if ($blockLink)
                continue;
            /** @var Post $page */
            $blockLink = new BlockLink();
            $blockLink->setBlock($block)
                ->setPost($page)
                ->setText($page->getTitle())
                ->setUrl($url);

            $this->em->persist($blockLink);
            $block->addLink($blockLink);
            $this->em->persist($block);
        }

        $this->em->flush();
    }

    private function parseConnectionString($connectionString)
    {
        preg_match_all("~([^\:]+)\:([^\@]+)?\@([^\/]+)\/(.*)~", $connectionString, $matches);

        if (isset($matches[1]))
            $this->database['user'] = $matches[1][0];
        else
            throw new \Exception("Hatalı parametre.");
        if (isset($matches[2]))
            $this->database['password'] = empty($matches[2][0]) ? null : $matches[2][0];
        else
            throw new \Exception("Hatalı parametre.");
        if (isset($matches[3]))
            $this->database['host'] = $matches[3][0];
        else
            throw new \Exception("Hatalı parametre.");
        if (isset($matches[4]))
            $this->database['dbname'] = $matches[4][0];
        else
            throw new \Exception("Hatalı parametre.");

        $this->database['charset'] = 'utf8';
    }
}
