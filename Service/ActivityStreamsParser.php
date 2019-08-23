<?php

namespace AV\ActivityPubBundle\Service;

use AV\ActivityPubBundle\DbType\ActivityType;
use AV\ActivityPubBundle\DbType\ActorType;
use AV\ActivityPubBundle\DbType\ObjectType;
use AV\ActivityPubBundle\Entity\Activity;
use AV\ActivityPubBundle\Entity\Actor;
use AV\ActivityPubBundle\Entity\BaseObject;
use AV\ActivityPubBundle\Entity\Place;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class ActivityStreamsParser
{
    protected $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function parse($data) : ?BaseObject
    {
        if( is_string($data) ) {
            return $this->getObjectFromUri($data);
        } else if( is_array($data) ) {
            if( ActivityType::includes($data['type']) ) {
                $activity = new Activity();
                $this->parseActivity($activity, $data);
                return $activity;
            } elseif ( ObjectType::includes($data['type']) ) {
                $object = null;
                // If a ID is defined, try to get an existing object
                if( $data['id'] ) {
                    $object = $this->getObjectFromUri($data['id']);
                    if( !$object ) $object = $this->em->getRepository(BaseObject::class)->find($data['id']);
                }
                if( $object ) {
                    // If object exists, unset the ID and type as we don't want it to change
                    unset( $data['id'] );
                    unset( $data['type'] );
                } else {
                    // No object found, create a new one
                    $object = $data['type'] === ObjectType::PLACE ? new Place() : new BaseObject();
                }
                $this->parseObject($object, $data);
                return $object;
            } elseif ( ActorType::includes($data['type']) ) {
                $actor = new Actor();
                $this->parseActor($actor, $data);
                return $actor;
            } else {
                throw new BadRequestHttpException("Unhandled object : {$data['type']}");
            }
        } else {
            throw new BadRequestHttpException("Can only parse URL or object");
        }
    }

    protected function parseActivity(Activity $activity, array $json)
    {
        $this->parseObject($activity, $json);

        if( array_key_exists('object', $json) ) {
            $object = $this->parse($json['object']);
            $activity->setObject($object);
        }
    }

    protected function parseObject(BaseObject $object, array $json)
    {
        $this->parseScalarValues($object, $json);

        if( array_key_exists('location', $json) ) {
            $location = $this->parse($json['location']);
            $object->setLocation($location);
        }

        if( array_key_exists('attachment', $json) ) {
            $attachment = $this->parse($json['attachment']);
            $object->setAttachment($attachment);
        }

        if( array_key_exists('tag', $json) ) {
            foreach( $json['tag'] as $tagValue ) {
                if( is_string($tagValue) ) {
                    $tag = $this->getObjectFromUri($tagValue);
                } else if( is_array($tagValue) ) {
                    if( $tagValue['type'] === ObjectType::TOPIC ) {
                        $tag = $this->em->getRepository(BaseObject::class)
                            ->findOneBy(['type' => ObjectType::TOPIC ,'name' => $tagValue['name']]);
                        if( !$tag ) {
                            $tag = $this->parse($tagValue);
                        }
                    } else {
                        throw new BadRequestHttpException('Bad tag type : ' . $tagValue['type']);
                    }
                } else {
                    throw new BadRequestHttpException('Bad tag : ' . gettype($tagValue));
                }

                $object->addTag($tag);
            }
        }
    }

    protected function parseActor(Actor $actor, array $json)
    {
        $this->parseObject($actor, $json);
    }

    protected function parseScalarValues(BaseObject $object, $json)
    {
        foreach( $this->getFieldTypes(get_class($object)) as $fieldName => $fieldType) {
            if( !array_key_exists($fieldName, $json) ) continue;

            switch($fieldType) {
                case "string":
                case "text":
                case "float":
                case "integer":
                    $object->set($fieldName, $json[$fieldName]);
                    break;

                case "datetime":
                    $object->set($fieldName, new \DateTime($json[$fieldName]));
                    break;

                default:
                    // Do nothing
            }
        }
    }

    protected function getFieldTypes($className)
    {
        $fieldTypes = [];
        $metadata = $this->em->getMetadataFactory()->getMetadataFor($className);

        foreach( $metadata->getFieldNames() as $fieldName ) {
            $fieldTypes[$fieldName] = $metadata->getTypeOfField($fieldName);
        };

        return $fieldTypes;
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
}