<?php

namespace Okulbilisim\OjsToolsBundle\Command;

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

    protected function configure()
    {
        $this
            ->setName('ojs:import:journal')
            ->setDescription('Import journals')
            ->addArgument(
                'JournalId', InputArgument::REQUIRED, 'Journal ID at ');
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
                'password' => 'root',
                'host' => 'localhost',
                'dbname' => 'dergipark',
            ));


            $em = $this->getContainer()->get("doctrine.orm.entity_manager");


            $journal_raw = $connection->fetchAll('SELECT * FROM journals where journal_id=' . $id . '  limit 1;');


            /*
             * Journal details
             */
            $journal_details = $connection->fetchAll('select * from journal_settings where journal_id =' . $id);

            foreach($journal_details as $journal_detail){
                echo $journal_detail['setting_name'].":\n\n".$journal_detail['setting_value'];
                echo "\n=====================\n";
            }

            /*

            $journal_detail[allowRegAuthor']
            $journal_detail['allowRegReader']
            $journal_detail['allowRegReviewer']
            $journal_detail['authorSelectsEditor']
            $journal_detail['boardEnabled']
            $journal_detail['categories']
            $journal_detail['contactEmail']
            $journal_detail['contactFax']
            $journal_detail['contactName']
            $journal_detail['contactPhone']
            $journal_detail['contributors']
            $journal_detail['copyrightNoticeAgree']
            $journal_detail['copySubmissionAckAddress']
            $journal_detail['copySubmissionAckPrimaryContact']
            $journal_detail['copySubmissionAckSpecified']
            $journal_detail['crossrefPassword']
            $journal_detail['crossrefUsername']
            $journal_detail['disableUserReg']
            $journal_detail['displayCurrentIssue']
            $journal_detail['emailSignature']
            $journal_detail['enableAnnouncements']
            $journal_detail['enableAnnouncementsHomepage']
            $journal_detail['enableComments']
            $journal_detail['enableLockss']
            $journal_detail['enablePageNumber']
            $journal_detail['enablePublicArticleId']
            $journal_detail['enablePublicGalleyId']
            $journal_detail['enablePublicIssueId']
            $journal_detail['enablePublicSuppFileId']
            $journal_detail['envelopeSender']
            $journal_detail['fastTrackFee']
            $journal_detail['historyDetails']
            $journal_detail['includeCreativeCommons']
            $journal_detail['initialNumber']
            $journal_detail['initialVolume']
            $journal_detail['initialYear']
            $journal_detail['isActiveOnOjs']
            $journal_detail['issuePerVolume']
            $journal_detail['itemsPerPage']
            $journal_detail['journalStyleSheet']
            $journal_detail['journalTheme']
            $journal_detail['mailingAddress']
            $journal_detail['mailSubmissionsToReviewers']
            $journal_detail['membershipFee']
            $journal_detail['metaCitationOutputFilterId']
            $journal_detail['metaCitations']
            $journal_detail['metaCoverage']
            $journal_detail['metaDiscipline']
            $journal_detail['metaSubject']
            $journal_detail['metaSubjectClass']
            $journal_detail['metaType']
            $journal_detail['notifyAllAuthorsOnDecision']
            $journal_detail['numAnnouncementsHomepage']
            $journal_detail['numDaysBeforeInviteReminder']
            $journal_detail['numDaysBeforeSubmitReminder']
            $journal_detail['numPageLinks']
            $journal_detail['numWeeksPerReview']
            $journal_detail['onlineIssn']
            $journal_detail['printIssn']
            $journal_detail['provideRefLinkInstructions']
            $journal_detail['publicationFee']
            $journal_detail['publicationFormatNumber']
            $journal_detail['publicationFormatTitle']
            $journal_detail['publicationFormatVolume']
            $journal_detail['publicationFormatYear']
            $journal_detail['publisherInstitution']
            $journal_detail['publisherType']
            $journal_detail['publisherUrl']
            $journal_detail['publishingMode']
            $journal_detail['purchaseArticleFee']
            $journal_detail['rateReviewerOnQuality']
            $journal_detail['remindForInvite']
            $journal_detail['remindForSubmit']
            $journal_detail['requireAuthorCompetingInterests']
            $journal_detail['requireReviewerCompetingInterests']
            $journal_detail['restrictArticleAccess']
            $journal_detail['restrictReviewerFileAccess']
            $journal_detail['restrictSiteAccess']
            $journal_detail['reviewerAccessKeysEnabled']
            $journal_detail['reviewerDatabaseLinks']
            $journal_detail['rtAbstract']
            $journal_detail['rtAddComment']
            $journal_detail['rtCaptureCite']
            $journal_detail['rtDefineTerms']
            $journal_detail['rtEmailAuthor']
            $journal_detail['rtEmailOthers']
            $journal_detail['rtEnabled']
            $journal_detail['rtFindingReferences']
            $journal_detail['rtPrinterFriendly']
            $journal_detail['rtSharingBrand']
            $journal_detail['rtSharingButtonStyle']
            $journal_detail['rtSharingDropDown']
            $journal_detail['rtSharingDropDownMenu']
            $journal_detail['rtSharingEnabled']
            $journal_detail['rtSharingLanguage']
            $journal_detail['rtSharingLogo']
            $journal_detail['rtSharingLogoBackground']
            $journal_detail['rtSharingLogoColor']
            $journal_detail['rtSharingUserName']
            $journal_detail['rtSupplementaryFiles']
            $journal_detail['rtVersionId']
            $journal_detail['rtViewMetadata']
            $journal_detail['rtViewReviewPolicy']
            $journal_detail['showEnsuringLink']
            $journal_detail['showGalleyLinks']
            $journal_detail['sponsors']
            $journal_detail['statCountAccept']
            $journal_detail['statCountDecline']
            $journal_detail['statCountRevise']
            $journal_detail['statDaysPerReview']
            $journal_detail['statDaysToPublication']
            $journal_detail['statItemsPublished']
            $journal_detail['statNumPublishedIssues']
            $journal_detail['statNumSubmissions']
            $journal_detail['statPeerReviewed']
            $journal_detail['statRegisteredReaders']
            $journal_detail['statRegisteredUsers']
            $journal_detail['statSubscriptions']
            $journal_detail['statViews']
            $journal_detail['submissionFee']
            $journal_detail['supportedFormLocales']
            $journal_detail['supportedLocales']
            $journal_detail['supportedSubmissionLocales']
            $journal_detail['supportEmail']
            $journal_detail['supportName']
            $journal_detail['supportPhone']
            $journal_detail['templates']
            $journal_detail['total_downloads']
            $journal_detail['total_views']
            $journal_detail['useCopyeditors']
            $journal_detail['useEditorialBoard']
            $journal_detail['useLayoutEditors']
            $journal_detail['useProofreaders']
            $journal_detail['volumePerYear']
            $journal_detail['abbreviation']
            $journal_detail['authorGuidelines']
            $journal_detail['competingInterestGuidelines']
            $journal_detail['copyrightNotice']
            $journal_detail['customAboutItems']
            $journal_detail['description']
            $journal_detail['focusScopeDesc']
            $journal_detail['history']
            $journal_detail['homeHeaderTitle']
            $journal_detail['homeHeaderTitleImage']
            $journal_detail['homeHeaderTitleType']
            $journal_detail['initials']
            $journal_detail['journalPageFooter']
            $journal_detail['journalThumbnail']
            $journal_detail['metaDisciplineExamples']
            $journal_detail['metaSubjectExamples']
            $journal_detail['navItems']
            $journal_detail['pageHeaderTitle']
            $journal_detail['pageHeaderTitleImage']
            $journal_detail['pageHeaderTitleType']
            $journal_detail['searchDescription']
            $journal_detail['searchKeywords']
            $journal_detail['submissionChecklist']
            $journal_detail['title']
            $journal_detail['abbreviation']
            $journal_detail['announcementsIntroduction']
            $journal_detail['authorGuidelines']
            $journal_detail['authorInformation']
            $journal_detail['authorSelfArchivePolicy']
            $journal_detail['competingInterestGuidelines']
            $journal_detail['copyeditInstructions']
            $journal_detail['copyrightNotice']
            $journal_detail['customAboutItems']
            $journal_detail['description']
            $journal_detail['donationFeeDescription']
            $journal_detail['donationFeeName']
            $journal_detail['fastTrackFeeDescription']
            $journal_detail['fastTrackFeeName']
            $journal_detail['focusScopeDesc']
            $journal_detail['history']
            $journal_detail['homeHeaderTitle']
            $journal_detail['homeHeaderTitleImage']
            $journal_detail['homeHeaderTitleImageAltText']
            $journal_detail['homeHeaderTitleType']
            $journal_detail['initials']
            $journal_detail['journalPageHeader']
            $journal_detail['journalThumbnail']
            $journal_detail['librarianInformation']
            $journal_detail['lockssLicense']
            $journal_detail['membershipFeeDescription']
            $journal_detail['membershipFeeName']
            $journal_detail['metaCitations']
            $journal_detail['metaDisciplineExamples']
            $journal_detail['metaSubjectExamples']
            $journal_detail['navItems']
            $journal_detail['openAccessPolicy']
            $journal_detail['pageHeaderTitle']
            $journal_detail['pageHeaderTitleImage']
            $journal_detail['pageHeaderTitleImageAltText']
            $journal_detail['pageHeaderTitleType']
            $journal_detail['proofInstructions']
            $journal_detail['publicationFeeDescription']
            $journal_detail['publicationFeeName']
            $journal_detail['purchaseArticleFeeDescription']
            $journal_detail['purchaseArticleFeeName']
            $journal_detail['readerInformation']
            $journal_detail['refLinkInstructions']
            $journal_detail['searchDescription']
            $journal_detail['searchKeywords']
            $journal_detail['submissionChecklist']
            $journal_detail['submissionFeeDescription']
            $journal_detail['submissionFeeName']
            $journal_detail['title']
            $journal_detail['waiverPolicy']

             */
            /*
             * Journal Create
             */


            $journal = new Journal();
            $journal->setTitle($journal_detail['title']);
            $journal->setTitleAbbr($journal_detail['abbreviation']);
            $journal->setDescription($journal_detail['description']);
            $journal->setSubtitle($journal_detail['homeHeaderTitle']);
            $journal->setIssn($journal_detail['printIssn']);
            $journal->setEissn($journal_detail['onlineIssn']);
            $journal->setPath($journal_raw['path']);
            // TODO setPeriod
            // $journal->setPeriod();
            $journal->setSlug($journal_raw['path']);
            $journal->setStatus(1);
            // TODO setUrl
            // $journal->setUrl();
            $journal->setTags($journal_detail['searchKeywords']);
            //$journal->setCountryId();

            $em->persist($journal);
            $em->flush();
            $journal_id = $journal->getId();



            /*
             * Journal users
             */
            $journal_users = $connection->fetchAll('select distinct user_id from roles where journal_id=' . $id . ' group by user_id order by user_id asc');
            $users_count = $connection->fetchArray('select count(*) from (select distinct user_id from roles where journal_id=' . $id . ' group by user_id order by user_id asc) b;');

            $i = 1;
            foreach ($journal_users as $journal_user) {
                $user = $connection->fetchAll('select * from users where user_id=' . $journal_user['user_id'] . ' limit 1;');

                /*
                 * User main info
                 */


                $user_entity = new User();
                $user_entity->setFirstName($user['first_name'] . ' ' . $user['middle_name']);
                $user_entity->setUsername($user['username']);
                $user_entity->setLastName($user['last_name']);
                $user_entity->setEmail($user['email']);
                $em->persist($user_entity);
                $em->flush();

                /*
                 * User roles with journal
                 */


                $user_role = new UserJournalRole();
                $user_role->setUserId($user_entity->getId());

                $user_role->setJournalId($journal_id);
                //$user_role->setRoleId();
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
                $author->setCountry(1);
                $author->setUserId($user_entity->getId());
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


        } catch (Exception $e) {
            print_r($e);
        }


    }

}