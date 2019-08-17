<?php

namespace AV\ActivityPubBundle\Repository;

use AV\ActivityPubBundle\DbType\ActivityType;
use AV\ActivityPubBundle\Entity\Actor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityRepository;

class ObjectRepository extends EntityRepository
{
    public function getCreatedObjects(Actor $actor): ArrayCollection
    {
        $result = $this->createQueryBuilder('object')
            ->join('object.activities', 'activity')
            ->where('activity.actor = :actor')
            ->andWhere('activity.type = :activityType')
            ->setParameters([
                'actor' => $actor,
                'activityType' => ActivityType::CREATE
            ])
            ->getQuery()
            ->getResult();

        return new ArrayCollection($result);
    }
}