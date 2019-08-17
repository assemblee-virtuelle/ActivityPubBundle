<?php

namespace AV\ActivityPubBundle\Serializer;

use AV\ActivityPubBundle\Entity\Activity;
use AV\ActivityPubBundle\Entity\BaseObject;
use AV\ActivityPubBundle\Entity\OrderedCollection;
use AV\ActivityPubBundle\Service\ActivityPubService;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class CollectionSerializer extends BaseSerializer
{
    /** @var ActivityPubService $activityPubService */
    private $activityPubService;

    /** @var ActivitySerializer $activitySerializer */
    private $activitySerializer;

    /** @var ObjectSerializer $objectSerializer */
    private $objectSerializer;

    public function __construct(ActivityPubService $activityPubService, ActivitySerializer $activitySerializer, ObjectSerializer $objectSerializer)
    {
        $this->activityPubService = $activityPubService;
        $this->activitySerializer = $activitySerializer;
        $this->objectSerializer = $objectSerializer;
    }

    /**
     * @param OrderedCollection $collection
     *
     * @return array
     */
    protected function getDataToSerialize($collection): ?array
    {
        $result = [
            "@context" => "https://www.w3.org/ns/activitystreams",
            "id" => $collection->getId(),
            "type" => "OrderedCollection",
            "totalItems" => count($collection->getObjects()),
            "orderedItems" => $collection->getObjects()->map(function (BaseObject $object) use ($collection) {
                if( is_a($object, Activity::class) ) {
                    $serialized = $this->activitySerializer->serialize($object);
                } else if( is_a($object, BaseObject::class) ) {
                    $serialized = $this->objectSerializer->serialize($object);
                } else {
                    throw new BadRequestHttpException("Cannot serialize object of type" . get_class($object));
                }
                return array_merge_recursive($serialized, $collection->getAdditional());
            })
        ];

        return $result;
    }
}
