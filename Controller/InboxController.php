<?php

namespace AV\ActivityPubBundle\Controller;

use AV\ActivityPubBundle\Entity\Actor;
use AV\ActivityPubBundle\Entity\OrderedCollection;
use AV\ActivityPubBundle\Serializer\CollectionSerializer;
use AV\ActivityPubBundle\Serializer\Serializable;
use AV\ActivityPubBundle\Service\ActivityPubService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Routing\Annotation\Route;

class InboxController extends BaseController
{
    /**
     * @Route("/actor/{username}/inbox", name="av_activitypub_actor_inbox", methods={"GET"})
     */
    public function readInbox(string $username)
    {
        $em = $this->getDoctrine()->getManager();
        /** @var ActivityPubService $activityPubService */
        $activityPubService = $this->container->get('activity_pub.service');
        /** @var CollectionSerializer $collectionSerializer */
        $collectionSerializer = $this->container->get('activity_pub.serializer.collection');

        /** @var Actor $actor */
        $actor = $em->getRepository(Actor::class)->findOneBy(['username' => $username]);
        if( !$actor ) throw new NotFoundHttpException();

        if( $this->container->get('kernel')->getEnvironment() === 'prod' ) {
            if( $actor !== $this->getLoggedActor() ) throw new UnauthorizedHttpException('You may only view your own inbox');
        }

        $actorUri = $activityPubService->getObjectUri($actor);

        $activities = $actor->getInboxActivities();

        $collection = new OrderedCollection($actorUri . "/inbox", $activities);

        return $this->json(new Serializable($collection, $collectionSerializer));
    }
}