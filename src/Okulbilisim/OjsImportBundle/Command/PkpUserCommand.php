<?php

namespace Okulbilisim\OjsImportBundle\Command;

use Okulbilisim\OjsImportBundle\Helper\ImportCommand;
use Okulbilisim\OjsImportBundle\Importer\PKP\UserImporter;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PkpUserCommand extends ImportCommand
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
        $um = $this->getContainer()->get('fos_user.user_manager');
        $tokenGenrator = $this->getContainer()->get('fos_user.util.token_generator');
        $importer = new UserImporter($this->connection, $this->em, $um, $tokenGenrator);
        $importer->importUser($id);
    }
}
