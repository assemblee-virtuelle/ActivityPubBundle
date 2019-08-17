<?php

namespace AV\ActivityPubBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity
 * @ORM\Table(name="place")
 */
class Place extends BaseObject
{
    /**
     * @ORM\Column(type="float", nullable=true)
     */
    protected $latitude;

    /**
     * @ORM\Column(type="float", nullable=true)
     */
    protected $longitude;

    public function __construct()
    {
        parent::__construct();
    }

    public function getLatitude() : ?float
    {
        return $this->latitude;
    }

    public function setLatitude(?float $latitude)
    {
        $this->latitude = $latitude;
        return $this;
    }

    public function getLongitude() : ?float
    {
        return $this->longitude;
    }

    public function setLongitude(?float $longitude)
    {
        $this->longitude = $longitude;
        return $this;
    }
}