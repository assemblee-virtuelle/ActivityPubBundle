<?php

namespace AV\ActivityPubBundle\Controller;

use AV\ActivityPubBundle\Entity\Actor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;

class BaseController extends AbstractController
{
    protected function parseBodyAsJson(Request $request): ?array
    {
        $content = $request->getContent();

        return !empty($content)
            ? json_decode($content, true)
            : [];
    }

    protected function getLoggedActor() : ?Actor
    {
        if( $this->getUser() ) {
            return $this->getUser()->getActor();
        } else {
            return null;
        }
    }
}