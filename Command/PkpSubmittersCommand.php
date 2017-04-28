<?php

namespace Vipa\ImportBundle\Command;


use Vipa\CoreBundle\Helper\StringHelper;
use Vipa\ImportBundle\Helper\ImportCommand;
use Vipa\ImportBundle\Importer\PKP\ArticleSubmitterImporter;
use Vipa\ImportBundle\Importer\PKP\UserImporter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Stopwatch\Stopwatch;

class PkpSubmittersCommand extends ImportCommand
{
    protected function configure()
    {
        $this
            ->setName('ojs:import:pkp:submitters')
            ->setDescription('Import article submitters from PKP/OJS');

        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $um = $this->getContainer()->get('fos_user.user_manager');
        $tokenGenrator = $this->getContainer()->get('fos_user.util.token_generator');
        $locale = $this->getContainer()->getParameter('locale');
        $userImporter = new UserImporter($this->connection, $this->em, $this->logger, $output, $um, $tokenGenrator, $locale);

        $importer = new ArticleSubmitterImporter($this->connection, $this->em,  $this->logger, $output);
        $stopwatch = new Stopwatch();
        $stopwatch->start('submitter_import');
        $importer->importArticleSubmitter($userImporter);
        $event = $stopwatch->stop('submitter_import');

        $output->writeln('Duration: ' . StringHelper::formatMilliseconds($event->getDuration()));
    }
}
