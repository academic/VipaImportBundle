<?php

namespace Okulbilisim\OjsImportBundle\Helper;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;

class ImportCommand extends ContainerAwareCommand
{
    protected function throwInvalidArgumentException($message)
    {
        throw new InvalidArgumentException($message);
    }
}