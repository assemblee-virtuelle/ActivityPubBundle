<?php

namespace AV\ActivityPubBundle\Serializer;

use Doctrine\Common\Collections\Collection;

/**
 * We encapsulate Serializer definitions in sub-classes of that one,
 * trying to have only one Serializer class per Entity,
 * and handling variations using object fields instead of new classes.
 *
 * To avoid proliferation of serialization formats,
 * constructors should be protected and only a handful of serializers should
 * be exposed as static properties
 * (actually function, due to php5.5 limitations).
 *
 * todo: replace static function endpoints with actual static init.
 */
abstract class BaseSerializer
{
    /**
     * Only a few vital fields are provided. Should only be used on a *ToMany relation,
     * to save some bandwidth if we're sure additional parameters won't be used by the caller.
     */
    public const FLAVOUR_SMALL = 'small';
    /**
     * A reasonable amount of fields are provided. Should fit for most usages.
     */
    public const FLAVOUR_MEDIUM = 'medium';
    /**
     * Same as MEDIUM, but with a few more fields, and/or *ToMany relations.
     */
    public const FLAVOUR_FULL = 'full';

    /**
     * Specify data which should be serialized to JSON.
     *
     * @see http://php.net/manual/en/jsonserializable.jsonserialize.php
     *
     * @param $entity
     *
     * @return mixed data which can be serialized by <b>json_encode</b>,
     *               which is a value of any type other than a resource
     *
     * @since 5.4.0
     */
    abstract protected function getDataToSerialize($entity): ?array;

    public function serialize($entity): ?array
    {
        if( !$entity ) return null;

        $result = $this->getDataToSerialize($entity);

        $result = array_filter($result, function($prop) {
            if( is_null($prop) ) {
                return false;
            } else if( $prop instanceof Collection && $prop->isEmpty() ) {
                return false;
            } else {
                return true;
            }
        });

        return $result;
    }

    /**
     * Throw an exception if $entity is not of type $type.
     */
    protected function ensureType($entity, $type): void
    {
        if (!is_a($entity, $type)) {
            throw new \InvalidArgumentException("Entity passed as parameter must be of type $type, ".($entity === null ? 'null' : get_class($entity)).' given');
        }
    }

    /**
     * Throw an exception if $flavour is not an element of $acceptedValues.
     */
    protected function ensureFlavour(string $flavour, array $acceptedValues): void
    {
        if (!in_array($flavour, $acceptedValues, true)) {
            throw new \InvalidArgumentException("Invalid flavour '$flavour', accepted values are ".implode(', ', $acceptedValues));
        }
    }

    /**
     * Returns date formatted if not null.
     */
    protected function formatDate(?\DateTime $dateTime): ?string
    {
        if ($dateTime === null) {
            return null;
        }

        return $dateTime->format(\DateTime::ATOM);
    }
}
