<?php

namespace Okulbilisim\OjsToolsBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction()
    {
        return $this->render('OkulbilisimOjsToolsBundle:Default:index.html.twig');
    }
}
