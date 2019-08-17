<?php

namespace AV\ActivityPubBundle\Service;

use AV\ActivityPubBundle\DbType\ActorType;
use AV\ActivityPubBundle\DbType\ObjectType;
use AV\ActivityPubBundle\DbType\ActivityType;
use AV\ActivityPubBundle\Entity\Activity;
use AV\ActivityPubBundle\Entity\Actor;
use AV\ActivityPubBundle\Entity\BaseObject;
use AV\ActivityPubBundle\Event\ActivityEvent;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Util\ClassUtils;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class ActivityPubService
{
    protected $em;

    protected $authorizationChecker;

    protected $serverBaseUrl;

    protected $parser;

    protected $dispatcher;

    public const PUBLIC_POST_URI = 'https://www.w3.org/ns/activitystreams#Public';

    public function __construct(RequestStack $requestStack, EntityManagerInterface $em, AuthorizationCheckerInterface $authorizationChecker, ActivityStreamsParser $parser, EventDispatcherInterface $dispatcher)
    {
        $this->em = $em;
        $this->authorizationChecker = $authorizationChecker;
        $this->serverBaseUrl = $requestStack->getCurrentRequest()->getSchemeAndHttpHost();
        $this->parser = $parser;
        $this->dispatcher = $dispatcher;
    }

    public function handleActivity(array $json, Actor $loggedActor, bool $massImport = false) : Activity
    {
        if( $json['@context'] !== 'https://www.w3.org/ns/activitystreams' ) {
            throw new BadRequestHttpException("Only ActivityStreams objects are allowed");
        }

        // If an object or actor is passed directly, wrap it inside a Create activity
        if( ObjectType::includes($json['type']) || ActorType::includes($json['type']) ) {
            $json = [
                'type' => ActivityType::CREATE,
                'to' => isset($json['to']) ? $json['to'] : null,
                'actor' => $json['attributedTo'],
                'object' => $json
            ];
        }

        $activityType = $json['type'];

        /** @var Activity $activity */
        $activity = $this->parser->parse($json);

        if( array_key_exists('actor', $json) ) {
            $postingActor = $this->getObjectFromUri($json['actor']);
            if( !$postingActor ) throw new BadRequestHttpException('Unknown actor : ' . $json['actor']);
            $activity->setActor($postingActor);

            // Make sure the logged actor has the right to post as the posting actor
            // TODO use something else than a voter so that someone else than the logged actor can post
            if( !$massImport && !$this->authorizationChecker->isGranted($activityType, $activity) ) {
                throw new UnauthorizedHttpException("You cannot post as {$activity->getActor()->getUsername()}");
            }
        } else {
            $activity->setActor($loggedActor);
        }

        //////////////////
        // SIDE EFFECTS
        //////////////////

        switch($activityType)
        {
            case ActivityType::CREATE:
                $this->handleCreate($activity, $json['object']);
                break;

            case ActivityType::FOLLOW:
                $this->handleFollow($activity, $json['object']);
                break;

            default:
                throw new BadRequestHttpException("Unhandled activity : $activityType");
        }

        $this->em->persist($activity);
        $this->em->flush();

        if( !$massImport ) {
            $activityEvent = new ActivityEvent($activity);
            $this->dispatcher->dispatch(ActivityEvent::NAME, $activityEvent);
        }

        return $activity;
    }

    protected function handleCreate(Activity $activity, array $objectJson)
    {
        // If we are creating an actor, set the logged user as the controlling actor
        if ( ActorType::includes($activity->getObject()->getType()) ) {
            if( !in_array($activity->getObject()->getType(), Actor::CONTROLLABLE_ACTORS) )
                throw new BadRequestHttpException("This type of actor cannot be created");
            $activity->getObject()->addControllingActor($activity->getActor());
        }

        $activity->setIsPublic(true);

        // Forward activity
        if( isset($objectJson['to']) ) {
            foreach( $objectJson['to'] as $actorUri ) {
                if( $actorUri === ActivityPubService::PUBLIC_POST_URI ) {
                    $activity->setIsPublic(true);
                } elseif ( $followers = $this->getFollowersFromUri($actorUri) ) {
                    foreach( $followers as $follower ) {
                        $activity->addReceivingActor($follower);
                    }
                } elseif ( $actor = $this->getObjectFromUri($actorUri) ) {
                    $activity->addReceivingActor($actor);
                } else {
                    throw new BadRequestHttpException("Unknown actor URI : $actorUri");
                }
            }
        }

        // TODO put this in an event listener
        $activity->setSummary('Nouvelle actualité postée par ' . $activity->getActor()->getName());
    }

    protected function handleFollow(Activity $activity, string $objectJson)
    {
        $actorToFollow = $this->getObjectFromUri($objectJson);
        $actorToFollow->addFollower($activity->getActor());
        $activity->setObject($activity->getActor());

        // TODO put this in an event listener
        $activity->setSummary($activity->getActor()->getName() . ' suit maintenant '  . $actorToFollow->getName());
    }

    public function getObjectFromUri(string $uri) : ?BaseObject
    {
        preg_match('/\/(actor|object|activity)\/([^\/]*)/', $uri, $matches );
        if( !$matches ) return null;

        switch($matches[1]) {
            case "object":
                return $this->em->getRepository(BaseObject::class)
                    ->findOneBy(['id' => $matches[2]]);

            case "actor":
                return $this->em->getRepository(Actor::class)
                    ->findOneBy(['username' => $matches[2]]);

            case "activity":
                return $this->em->getRepository(Activity::class)
                    ->findOneBy(['id' => $matches[2]]);

            default:
                return null;
        }
    }

    public function getFollowersFromUri(string $uri) : Collection
    {
        preg_match('/\/actor\/(\w*)\/followers/', $uri, $matches );
        if( !$matches ) return null;
        /** @var Actor $actor */
        $actor = $this->em->getRepository(Actor::class)
            ->findOneBy(['username' => $matches[1]]);
        return $actor->getFollowers();
    }

    public function getObjectUri($object) {
        switch( ClassUtils::getClass($object) ) {
            case 'AV\ActivityPubBundle\Entity\Activity':
                return $this->serverBaseUrl . '/activity/' . $object->getId();
                break;

            case 'AV\ActivityPubBundle\Entity\BaseObject':
            case 'AV\ActivityPubBundle\Entity\Place':
                return $this->serverBaseUrl . '/object/' . $object->getId();
                break;

            case 'AV\ActivityPubBundle\Entity\Actor':
                return $this->serverBaseUrl . '/actor/' . $object->getUsername();
                break;

            default:
                throw new BadRequestHttpException("Unknown object : " . ClassUtils::getClass($object) );
        }
    }
}