<?php

namespace AV\ActivityPubBundle\Serializer;

use AV\ActivityPubBundle\Entity\Activity;
use AV\ActivityPubBundle\Service\ActivityPubService;

class ActivitySerializer extends BaseSerializer
{
    /** @var string */
    private $flavour;

    /** @var ActivityPubService $activityPubService */
    private $activityPubService;

    /** @var ObjectSerializer $objectSerializer */
    private $objectSerializer;

    public function __construct($flavour, ActivityPubService $activityPubService, ObjectSerializer $objectSerializer)
    {
        $this->ensureFlavour($flavour, [self::FLAVOUR_SMALL, self::FLAVOUR_MEDIUM]);
        $this->flavour = $flavour;
        $this->activityPubService = $activityPubService;
        $this->objectSerializer = $objectSerializer;
    }

    /**
     * @param Activity $activity
     *
     * @return array
     */
    protected function getDataToSerialize($activity): ?array
    {
        $this->ensureType($activity, Activity::class);

        $activityUri = $this->activityPubService->getObjectUri($activity);

        $result = [
            "@context" => "https://www.w3.org/ns/activitystreams",
            "id" => $activityUri,
            "type" => $activity->getType(),
            "actor" => $activity->getActor() ? $this->activityPubService->getObjectUri($activity->getActor()) : null,
            "object" => $activity->getObject() ? $this->activityPubService->getObjectUri($activity->getObject()) : null
        ];

        if ($this->flavour === self::FLAVOUR_MEDIUM) {
            $result = array_merge($result, [
                "object" => $activity->getObject() ? $this->objectSerializer->serialize($activity->getObject()) : null
            ]);
        }

        return $result;
    }
}
