<?php

namespace AV\ActivityPubBundle\DbType;

class ActivityType extends EnumType
{
    protected $name = 'activity_type';

    public const ACCEPT = 'Accept';

    public const CREATE = 'Create';

    public const FOLLOW = 'Follow';

    public const UNDO = 'Undo';
}