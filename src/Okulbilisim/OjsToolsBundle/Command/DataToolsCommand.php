<?php

namespace Okulbilisim\OjsToolsBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use \Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Input\InputArgument;

class DataToolsCommand extends ContainerAwareCommand {

    protected function configure() {
        $this
                ->setName('ojs:install:data')
                ->setDescription('Add data')
                ->addArgument(
                        'instutionCode', InputArgument::REQUIRED, 'Instution code and also folder name under DataFixtures/');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $kernel = $this->getContainer()->get('kernel');
        $application = new \Symfony\Bundle\FrameworkBundle\Console\Application($kernel);
        $application->setAutoExit(false);
        $output->writeln('<info>Adding  data</info>');


        $files = [];
        while (false !== ($entry = readdir(__DIR__ . '/../DataFixtures/Alice/' . $input->getArgument('instutionCode') . '/'))) {
            $files[] = "$entry\n";
        }
        /*
          $manager = $this->get('h4cc_alice_fixtures.manager');
          $objects = $manager->loadFiles($files, 'yaml');
          $manager->persist($objects, true);
         */
        $output->writeln("\nDONE\n");
    }

}
