<?php

namespace Ojs\CliBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use \Symfony\Component\Console\Input\StringInput;

class DataToolsCommand extends ContainerAwareCommand {

    protected function configure() {
        $this
                ->setName('ojs:data:install')
                ->setDescription('Generate and save data for a specific  ');
    }

    protected function execute(InputInterface $input, OutputInterface $output) {
        $kernel = $this->getContainer()->get('kernel');
        $application = new \Symfony\Bundle\FrameworkBundle\Console\Application($kernel); 
        $output->writeln('<info>Adding  data</info>'); 
        
        $output->writeln("\nDONE\n");
    }

}
