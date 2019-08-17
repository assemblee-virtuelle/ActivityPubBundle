<?php

namespace AV\ActivityPubBundle\Serializer;

use AV\ActivityPubBundle\Entity\Actor;
use AV\ActivityPubBundle\Service\ActivityPubService;

class ActorSerializer extends BaseSerializer
{
    /** @var string */
    private $flavour;

    /** @var ActivityPubService $activityPubService */
    private $activityPubService;

    /** @var ObjectSerializer $objectSerializer */
    private $objectSerializer;

    public function __construct($flavour, ActivityPubService $activityPubService, ObjectSerializer $objectSerializer)
    {
        $this->ensureFlavour($flavour, [self::FLAVOUR_MEDIUM, self::FLAVOUR_FULL]);
        $this->flavour = $flavour;
        $this->activityPubService = $activityPubService;
        $this->objectSerializer = $objectSerializer;
    }

    /**
     * @param Actor $actor
     *
     * @return array
     */
    protected function getDataToSerialize($actor): ?array
    {
        $this->ensureType($actor, Actor::class);

        $actorUri = $this->activityPubService->getObjectUri($actor);

        $result = array_merge([
            "@context" => "https://www.w3.org/ns/activitystreams",
            "id" => $actorUri,
            "type" => $actor->getType(),
            "preferredUsername" => $actor->getUsername()
        ], $this->objectSerializer->serialize($actor));

        if ($this->flavour === self::FLAVOUR_FULL) {
            $result = array_merge($result, [
                "@context" => [
                    "https://www.w3.org/ns/activitystreams",
                    "https://w3id.org/security/v1"
                ],
                "inbox" => $actorUri . '/inbox',
                "outbox" => $actorUri . '/outbox',
                "followers" => $actorUri . '/followers',
                "following" => $actorUri . '/following',
                "created" => $actorUri . '/created',
                "publicKey" => [
                    "id" => $actorUri . "#main-key",
                    "owner" => $actorUri,
                    "publicKeyPem" => "-----BEGIN PUBLIC KEY-----...-----END PUBLIC KEY-----"
                ]
            ]);
        }

        return $result;
    }
}
