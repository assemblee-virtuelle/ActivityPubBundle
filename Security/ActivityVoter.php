<?php

namespace AV\ActivityPubBundle\Security;

use AV\ActivityPubBundle\DbType\ActivityType;
use AV\ActivityPubBundle\Entity\Activity;
use AV\ActivityPubBundle\Entity\Actor;
use AV\ActivityPubBundle\Entity\ActorUser;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class ActivityVoter extends Voter
{
    protected function supports($attribute, $subject): bool
    {
        // if the attribute isn't one we support, return false
        if (!ActivityType::includes($attribute)) {
            return false;
        }

        // only vote on Activity objects inside this voter
        if (!$subject instanceof Activity) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token): bool
    {
        /** @var ActorUser $user */
        $user = $token->getUser();

        // Our token implementation returns 'anon.' if no user logged in,
        // whereas our code expects null in that case.
        $loggedActor =  $user === 'anon.' ? null : $user->getActor();

        // Guaranteed by the supports() method
        /** @var Activity $activity */
        $activity = $subject;

        /** @var Actor $postingActor */
        $postingActor = $activity->getActor();

        if( $postingActor === $loggedActor ) {
            return true;
        } else if( in_array($postingActor->getType(), Actor::CONTROLLABLE_ACTORS) ) {
            return $this->hasControl($postingActor, $loggedActor);
        } else {
            return false;
        }
    }

    /*
     * Check if the $controllingActor has the right to control the $controlledActor
     */
    private function hasControl(Actor $controlledActor, Actor $controllingActor) : bool
    {
        if( $controlledActor->hasControllingActor($controllingActor) ){
            return true;
        } else {
            // Also look for parents
            /** @var Actor[] $controllingActors */
            $controllingActors = $controlledActor->getControllingActors();
            foreach( $controllingActors as $controllingActor ) {
                if( $this->hasControl($controlledActor, $controllingActor) ) {
                    return true;
                }
            }
        }

        return false;
    }
}