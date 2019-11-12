<?php

namespace AV\ActivityPubBundle\Repository;

use AV\ActivityPubBundle\DbType\ActivityType;
use AV\ActivityPubBundle\DbType\ObjectType;
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
            ->andWhere('object.type != :tombstone')
            ->setParameters([
                'actor' => $actor,
                'activityType' => ActivityType::CREATE,
                'tombstone' => ObjectType::TOMBSTONE
            ])
            ->addOrderBy('object.updated', 'DESC')
            ->addOrderBy('object.published', 'DESC')
            ->getQuery()
            ->getResult();

        return new ArrayCollection($result);
    }
}