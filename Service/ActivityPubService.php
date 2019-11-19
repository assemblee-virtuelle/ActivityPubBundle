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
use Psr\Log\LoggerInterface;
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

    protected $logger;

    public const PUBLIC_POST_URI = 'https://www.w3.org/ns/activitystreams#Public';

    public function __construct(RequestStack $requestStack, EntityManagerInterface $em, AuthorizationCheckerInterface $authorizationChecker, ActivityStreamsParser $parser, EventDispatcherInterface $dispatcher, LoggerInterface $logger)
    {
        $this->em = $em;
        $this->authorizationChecker = $authorizationChecker;
        $this->serverBaseUrl = $requestStack->getCurrentRequest() ? $requestStack->getCurrentRequest()->getSchemeAndHttpHost() : "http://localhost";
        $this->parser = $parser;
        $this->dispatcher = $dispatcher;
        $this->logger = $logger;
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

        // Set sender
        if( array_key_exists('actor', $json) && $json['actor'] ) {
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

        // Set recipients
        if( array_key_exists('to', $json) && $json['to'] ) {
            foreach( $json['to'] as $actorUri ) {
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
        } else {
            // TODO see what ActivityPub standards say
            $activity->setIsPublic(true);
        }

        //////////////////
        // SIDE EFFECTS
        //////////////////

        switch($activityType)
        {
            case ActivityType::CREATE:
                $this->handleCreate($activity, $json['object']);
                break;

            case ActivityType::UPDATE:
                $this->handleUpdate($activity, $json['object']);
                break;

            case ActivityType::DELETE:
                $this->handleDelete($activity, $json['object']);
                break;

            case ActivityType::FOLLOW:
                $this->handleFollow($activity, $json['object']);
                break;

            case ActivityType::UNDO:
                $this->handleUndo($activity, $json['object']);
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

        // TODO put this in an event listener
        $activity->setSummary('Nouvel objet posté par ' . $activity->getActor()->getName());
    }

    protected function handleUpdate(Activity $activity, array $objectJson)
    {
        $activity->setSummary('Objet mise à jour par ' . $activity->getActor()->getName());
    }

    protected function handleDelete(Activity $activity, string $objectJson)
    {
        $activity->getObject()->delete();

        $activity->setSummary('Objet effacé par ' . $activity->getActor()->getName());
    }

    protected function handleFollow(Activity $activity, string $objectJson)
    {
        $actorToFollow = $this->getObjectFromUri($objectJson);
        $actorToFollow->addFollower($activity->getActor());

        // TODO put this in an event listener
        $activity->setSummary($activity->getActor()->getName() . ' suit maintenant '  . $actorToFollow->getName());
    }

    protected function handleUndo(Activity $activity, string $objectJson)
    {
        /** @var Activity $activityToUndo */
        $activityToUndo = $this->getObjectFromUri($objectJson);

        switch($activityToUndo->getType()) {
            case ActivityType::FOLLOW:
                $actorToUnfollow = $activityToUndo->getObject();
                $actorToUnfollow->removeFollower($activity->getActor());
                $this->em->persist($actorToUnfollow);
                // TODO put this in an event listener
                $activity->setSummary($activity->getActor()->getName() . ' ne suit plus '  . $actorToUnfollow->getName());
                break;

            default:
                throw new BadRequestHttpException("We cannot undo this type of activity : " . $activityToUndo->getType());
        }
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

    public function getFollowersFromUri(string $uri) : ?Collection
    {
        preg_match('/\/actor\/([\w-_]*)\/followers/', $uri, $matches );
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