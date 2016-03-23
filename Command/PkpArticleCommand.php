<?php

namespace Ojs\ImportBundle\Command;

use Ojs\ImportBundle\Helper\ImportCommand;
use Ojs\ImportBundle\Importer\PKP\ArticleImporter;
use Ojs\ImportBundle\Importer\PKP\UserImporter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PkpArticleCommand extends ImportCommand
{
    protected function configure()
    {
        $this
            ->setName('ojs:import:pkp:article')
            ->setDescription('Import an article from PKP/OJS')
            ->addArgument('id', InputArgument::REQUIRED, 'Article ID')
            ->addArgument('journal', InputArgument::REQUIRED, 'New journal ID')
            ->addArgument('issue', InputArgument::REQUIRED, 'New issue ID (string null will leave the field empty)')
            ->addArgument('section', InputArgument::REQUIRED, 'New section ID (string null will leave the field empty)');
        parent::configure();
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        parent::execute($input, $output);

        $um = $this->getContainer()->get('fos_user.user_manager');
        $locale = $this->getContainer()->getParameter('locale');
        $tokenGenrator = $this->getContainer()->get('fos_user.util.token_generator');
        $ui = new UserImporter($this->connection, $this->em, $this->logger, $output, $um, $tokenGenrator, $locale);
        $ai = new ArticleImporter($this->connection, $this->em, $this->logger, $output, $ui);

        $id = $input->getArgument('id');
        $journal = $input->getArgument('journal');
        $issue = $input->getArgument('issue') != 'null' ? $input->getArgument('issue') : null;
        $section = $input->getArgument('section') != 'null' ? $input->getArgument('section') : null;

        $ai->importArticle($id, $journal, $issue, $section);
        $this->em->flush();
    }
}
