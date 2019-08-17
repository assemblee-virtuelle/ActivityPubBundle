<?php

namespace AV\ActivityPubBundle\Serializer;

class Serializable implements \JsonSerializable
{
    /** @var object */
    private $entity;

    /** @var BaseSerializer */
    private $serializer;

    /** @var array */
    private $additional;

    public function __construct($entity, $serializer, array $additional = [])
    {
        $this->entity = $entity;
        $this->serializer = $serializer;
        $this->additional = $additional;
    }

    /**
     * Specify data which should be serialized to JSON.
     *
     * @see http://php.net/manual/en/jsonserializable.jsonserialize.php
     *
     * @return mixed data which can be serialized by <b>json_encode</b>,
     *               which is a value of any type other than a resource
     *
     * @since 5.4.0
     */
    public function jsonSerialize()
    {
        $result = $this->serializer->serialize($this->entity);

        return array_merge_recursive($result, $this->additional);
    }
}
