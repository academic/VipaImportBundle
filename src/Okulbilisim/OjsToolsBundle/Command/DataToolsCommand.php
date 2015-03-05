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
        $path = __DIR__ . '/../DataFixtures/' . $input->getArgument('instutionCode') . '/*.yml';
        $output->writeln("reading ymls from " . $path);
        $files = glob($path);
        if (!empty($files) ){
            $filesStr = implode(' ', $files);
            $application->run(new StringInput('h4cc_alice_fixtures:load:files --manager=default --type=yaml --seed=100 --no-persist ' . $filesStr));
            $output->writeln("\nDONE\n");
        }
    }

}
