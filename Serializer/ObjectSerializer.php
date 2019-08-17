<?php

namespace AV\ActivityPubBundle\Serializer;

use AV\ActivityPubBundle\DbType\ObjectType;
use AV\ActivityPubBundle\Entity\BaseObject;
use AV\ActivityPubBundle\Entity\Place;
use AV\ActivityPubBundle\Service\ActivityPubService;

class ObjectSerializer extends BaseSerializer
{
    /** @var string */
    private $flavour;

    /** @var ActivityPubService $activityPubService */
    private $activityPubService;

    public function __construct($flavour, ActivityPubService $activityPubService)
    {
        $this->ensureFlavour($flavour, [self::FLAVOUR_MEDIUM]);
        $this->flavour = $flavour;
        $this->activityPubService = $activityPubService;
    }

    /**
     * @param BaseObject $object
     *
     * @return array
     */
    public function getDataToSerialize($object): ?array
    {
        $this->ensureType($object, BaseObject::class);

        $objectUri = $this->activityPubService->getObjectUri($object);

        $result = [
            "@context" => "https://www.w3.org/ns/activitystreams",
            "id" => $objectUri,
            "type" => $object->getType(),
            "name" => $object->getName(),
            "summary" => $object->getSummary(),
            "content" => $object->getContent(),
            "image" => $object->getImage(),
            "url" => $object->getUrl(),
            "published" => $object->getPublished(),
            "updated" => $object->getUpdated(),
            "location" => $this->serialize($object->getLocation()),
            "tag" => $object->getTags()->map(function( $tag ) { return $this->serialize($tag); })
        ];

        if( $object->getType() === ObjectType::PLACE ) {
            /** @var Place $object */
            $result = array_merge($result, [
                "latitude" => $object->getLatitude(),
                "longitude" => $object->getLongitude(),
            ]);
        }

        return $result;
    }
}
