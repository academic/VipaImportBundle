<?php

namespace Okulbilisim\OjsToolsBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;
use Okulbilisim\OjsToolsBundle\Command\DataToolsCommand;

class OkulbilisimOjsToolsBundle extends Bundle {

    public function registerCommands(Application $application) {
        $application->add(new DataToolsCommand());
    }

}
