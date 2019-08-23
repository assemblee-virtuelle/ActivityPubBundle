<?php

namespace AV\ActivityPubBundle\Controller;

use AV\ActivityPubBundle\DbType\ActivityType;
use AV\ActivityPubBundle\Entity\Activity;
use AV\ActivityPubBundle\Entity\Actor;
use AV\ActivityPubBundle\Entity\OrderedCollection;
use AV\ActivityPubBundle\Repository\ActivityRepository;
use AV\ActivityPubBundle\Serializer\CollectionSerializer;
use AV\ActivityPubBundle\Serializer\Serializable;
use AV\ActivityPubBundle\Service\ActivityPubService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class OutboxController extends BaseController
{
    /**
     * @Route("/actor/{username}/outbox", name="av_activitypub_post_actor_outbox", methods={"POST"})
     */
    public function postActivity(string $username, Request $request)
    {
        /** @var ActivityPubService $activityPubService */
        $activityPubService = $this->container->get('activity_pub.service');

        $actor = $this->getLoggedActor();
        $json = $this->parseBodyAsJson($request);

        if( $actor->getUsername() !== $username ) {
            throw new AccessDeniedHttpException("You are not allowed to post to someone else's outbox");
        }

        if( !$json ) {
            throw new BadRequestHttpException("You must post a JSON object to this endpoint");
        }

        $activity = $activityPubService->handleActivity($json, $actor);

        switch($activity->getType())
        {
            case ActivityType::CREATE:
                $status = Response::HTTP_CREATED;
                break;

            case ActivityType::DELETE:
                $status = Response::HTTP_GONE;
                break;

            default:
                $status = Response::HTTP_OK;
        }

        return new Response(
            null,
            $status,
            ['Location' => $activityPubService->getObjectUri($activity)]
        );
    }

    /**
     * @Route("/actor/{username}/outbox", name="av_activitypub_get_actor_outbox", methods={"GET"})
     */
    public function readOutbox(string $username)
    {
        $em = $this->getDoctrine()->getManager();
        /** @var ActivityPubService $activityPubService */
        $activityPubService = $this->container->get('activity_pub.service');
        /** @var CollectionSerializer $collectionSerializer */
        $collectionSerializer = $this->container->get('activity_pub.serializer.collection');
        /** @var ActivityRepository $activityRepo */
        $activityRepo = $em->getRepository(Activity::class);

        /** @var Actor $actor */
        $actor = $em->getRepository(Actor::class)->findOneBy(['username' => $username]);
        if( !$actor ) throw new NotFoundHttpException();

        $actorUri = $activityPubService->getObjectUri($actor);

        $activities = $activityRepo->getOutboxActivities($actor, $this->getLoggedActor());

        $collection = new OrderedCollection($actorUri . "/outbox", $activities);

        return $this->json(new Serializable($collection, $collectionSerializer));
    }
}