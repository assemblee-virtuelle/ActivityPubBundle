<?php

namespace AV\ActivityPubBundle\Entity;

use Doctrine\Common\Collections\Collection;

class OrderedCollection
{
    /**
     * @var string $id
     */
    protected $id;

    /**
     * @var BaseObject[] $objects
     */
    protected $objects;

    /**
     * @var array $additional
     */
    private $additional;

    public function __construct(string $id, Collection $objects, array $additional = [])
    {
        $this->id = $id;
        $this->objects = $objects;
        $this->additional = $additional;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getObjects()
    {
        return $this->objects;
    }

    public function getAdditional()
    {
        return $this->additional;
    }
}