<?php

namespace AV\ActivityPubBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @ORM\MappedSuperclass
 */
abstract class ActorUser implements UserInterface
{
    /**
     * @var Actor
     * @ORM\OneToOne(targetEntity="AV\ActivityPubBundle\Entity\Actor", cascade={"persist"})
     */
    protected $actor;

    public function __construct(Actor $actor)
    {
        $this->actor = $actor;
    }

    public function setActor(Actor $actor)
    {
        $this->actor = $actor;
        return $this;
    }

    public function getActor()
    {
        return $this->actor;
    }

    public function getUsername()
    {
        return $this->actor->getUsername();
    }
}