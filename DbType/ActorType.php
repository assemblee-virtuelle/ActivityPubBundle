<?php

namespace AV\ActivityPubBundle\DbType;

class ActorType extends EnumType
{
    protected $name = 'actor_type';

    public const APPLICATION = 'Application';

    public const GROUP = 'Group';

    public const ORGANIZATION = 'Organization';

    public const PERSON = 'Person';

    public const SERVICE = 'Service';

    // TODO put this in a PairActorType extension

    public const PROJECT = 'Project';
}