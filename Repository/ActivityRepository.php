<?php

namespace AV\ActivityPubBundle\Repository;

use AV\ActivityPubBundle\Entity\Actor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityRepository;

class ActivityRepository extends EntityRepository
{
    public function getOutboxActivities(Actor $actor, ?Actor $loggedActor): ArrayCollection
    {
        $result = $this->createQueryBuilder('activity')
            ->leftJoin('activity.receivingActors', 'receivingActor')
            ->where('activity.actor = :actor')
            ->andWhere('activity.isPublic = true OR receivingActor = :loggedActor')
            ->setParameters([
                'actor' => $actor,
                'loggedActor' => $loggedActor
            ])
            ->getQuery()
            ->getResult();

        return new ArrayCollection($result);
    }
}