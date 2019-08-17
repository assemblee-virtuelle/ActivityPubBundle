<?php

namespace AV\ActivityPubBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass="AV\ActivityPubBundle\Repository\ActivityRepository")
 * @ORM\Table(name="activity")
 */
class Activity extends BaseObject
{
    /**
     * @ORM\ManyToOne(targetEntity="Actor", inversedBy="outboxActivities")
     */
    private $actor;

    /**
     * Many Activities are posted to many Actors's inboxes
     * @ORM\ManyToMany(targetEntity="Actor", inversedBy="inboxActivities")
     * @ORM\JoinTable(name="activity_receiving_actor")
     */
    private $receivingActors;

    /**
     * @ORM\Column(name="is_public", type="boolean")
     */
    private $isPublic;

    /**
     * Each object may be linked to one or more activity
     * @ORM\ManyToOne(targetEntity="BaseObject", inversedBy="activities", cascade={"persist"})
     */
    private $object;

    public function __construct()
    {
        parent::__construct();
        $this->isPublic = false;
        $this->receivingActors = new ArrayCollection();
    }

    public function getActor() : ?Actor
    {
        return $this->actor;
    }

    public function setActor(Actor $actor) : self
    {
        $this->actor = $actor;
        return $this;
    }

    public function getReceivingActors() : Collection
    {
        return $this->receivingActors;
    }

    public function addReceivingActor(Actor $actor) : self
    {
        if (!$this->receivingActors->contains($actor)) {
            $actor->addInboxActivity($this);
            $this->receivingActors[] = $actor;
        }
        return $this;
    }

    public function removeActorInbox(Actor $actor) : self
    {
        if ($this->receivingActors->contains($actor)) {
            $this->receivingActors->removeElement($actor);
        }
        return $this;
    }

    public function getIsPublic() : bool
    {
        return $this->isPublic;
    }

    public function setIsPublic($isPublic) : self
    {
        $this->isPublic = $isPublic;
        return $this;
    }

    public function getObject() : ?BaseObject
    {
        return $this->object;
    }

    public function setObject(?BaseObject $object) : self
    {
        $this->object = $object;
        return $this;
    }
}