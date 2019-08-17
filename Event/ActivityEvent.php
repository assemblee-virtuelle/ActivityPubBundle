<?php

namespace AV\ActivityPubBundle\Event;

use AV\ActivityPubBundle\Entity\Activity;
use Symfony\Component\EventDispatcher\Event;

class ActivityEvent extends Event
{
    public const NAME = 'activitypub.activity';

    /** @var Activity $activity */
    protected $activity;

    public function __construct(Activity $activity)
    {
        $this->activity = $activity;
    }

    public function getActivity() : Activity
    {
        return $this->activity;
    }
}