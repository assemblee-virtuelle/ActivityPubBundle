<?php

namespace AV\ActivityPubBundle\DbType;

class ObjectType extends EnumType
{
    protected $name = 'object_type';

    public const DOCUMENT = 'Document';

    public const NOTE = 'Note';

    public const PLACE = 'Place';

    public const TOMBSTONE = 'Tombstone';

    // TODO put this in a PairActorType extension

    public const TOPIC = 'Topic';
}