<?php

namespace AV\ActivityPubBundle\Controller;

use AV\ActivityPubBundle\Entity\Actor;
use AV\ActivityPubBundle\Entity\BaseObject;
use AV\ActivityPubBundle\Entity\OrderedCollection;
use AV\ActivityPubBundle\Repository\ObjectRepository;
use AV\ActivityPubBundle\Serializer\ActorSerializer;
use AV\ActivityPubBundle\Serializer\CollectionSerializer;
use AV\ActivityPubBundle\Serializer\Serializable;
use AV\ActivityPubBundle\Service\ActivityPubService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class ActorController extends BaseController
{
    /**
     * @Route("/actor/{username}", name="av_activitypub_actor_profile", methods={"GET"})
     */
    public function actorProfile(string $username)
    {
        $em = $this->getDoctrine()->getManager();
        /** @var ActorSerializer $actorSerializer */
        $actorSerializer = $this->container->get('activity_pub.serializer.actor.full');

        /** @var Actor $actor */
        $actor = $em->getRepository(Actor::class)->findOneBy(['username' => $username]);
        if( !$actor ) throw new NotFoundHttpException();

        return $this->json(new Serializable($actor, $actorSerializer));
    }

    /**
     * @Route("/actor/{username}/created", name="av_activitypub_actor_created", methods={"GET"})
     */
    public function actorCreated(string $username)
    {
        $em = $this->getDoctrine()->getManager();
        /** @var ObjectRepository $objectRepo */
        $objectRepo = $em->getRepository(BaseObject::class);
        /** @var ActivityPubService $activityPubService */
        $activityPubService = $this->container->get('activity_pub.service');
        /** @var CollectionSerializer $collectionSerializer */
        $collectionSerializer = $this->container->get('activity_pub.serializer.collection');

        /** @var Actor $actor */
        $actor = $em->getRepository(Actor::class)->findOneBy(['username' => $username]);
        if( !$actor ) throw new NotFoundHttpException();

        $actorUri = $activityPubService->getObjectUri($actor);

        $createdObjects = $objectRepo->getCreatedObjects($actor);

        $collection = new OrderedCollection($actorUri . "/created", $createdObjects, array( 'attributedTo' => $actorUri ));

        return $this->json(new Serializable($collection, $collectionSerializer));
    }

    /**
     * @Route("/actor/{username}/followers", name="av_activitypub_actor_followers", methods={"GET"})
     */
    public function actorFollowers(string $username)
    {
        $em = $this->getDoctrine()->getManager();
        /** @var ActivityPubService $activityPubService */
        $activityPubService = $this->container->get('activity_pub.service');
        /** @var CollectionSerializer $collectionSerializer */
        $collectionSerializer = $this->container->get('activity_pub.serializer.collection');

        /** @var Actor $actor */
        $actor = $em->getRepository(Actor::class)->findOneBy(['username' => $username]);
        if( !$actor ) throw new NotFoundHttpException();

        $actorUri = $activityPubService->getObjectUri($actor);

        $followers = $actor->getFollowers();

        $collection = new OrderedCollection($actorUri . "/followers", $followers);

        return $this->json(new Serializable($collection, $collectionSerializer));
    }

    /**
     * @Route("/actor/{username}/following", name="av_activitypub_actor_following", methods={"GET"})
     */
    public function actorFollowing(string $username)
    {
        $em = $this->getDoctrine()->getManager();
        /** @var ActivityPubService $activityPubService */
        $activityPubService = $this->container->get('activity_pub.service');
        /** @var CollectionSerializer $collectionSerializer */
        $collectionSerializer = $this->container->get('activity_pub.serializer.collection');

        /** @var Actor $actor */
        $actor = $em->getRepository(Actor::class)->findOneBy(['username' => $username]);
        if( !$actor ) throw new NotFoundHttpException();

        $actorUri = $activityPubService->getObjectUri($actor);

        $followers = $actor->getFollowing();

        $collection = new OrderedCollection($actorUri . "/following", $followers);

        return $this->json(new Serializable($collection, $collectionSerializer));
    }
}