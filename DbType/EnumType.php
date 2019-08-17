<?php

namespace AV\ActivityPubBundle\DbType;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use ReflectionClass;

abstract class EnumType extends Type
{
    protected $name;

    /**
     * Return all constant values define in current class (excluding constants in parent classes).
     *
     * For example:
     *
     * class A
     * {
     *  const $constA = 'A!';
     * }
     *
     * class B extends A
     * {
     *  const $constB1 = 'B1!';
     *  const $constB2 = 'B2!';
     * }
     *
     * B::getValues() will return ['B1!', 'B2!']
     */
    public static function getValues(): array
    {
        $itemClass = new ReflectionClass(static::class);

        $constants = array_values($itemClass->getConstants());
        $parentConstants = array_values($itemClass->getParentClass()->getConstants());

        if (array_slice($constants, -count($parentConstants)) != $parentConstants) {
            throw new \RuntimeException($itemClass->getName().' contains a reserved constant of class Type');
        }

        return array_slice($constants, 0, count($constants) - count($parentConstants));
    }

    public static function includes($value): bool
    {
        return in_array($value, self::getValues());
    }

    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform): string
    {
        $values = array_map(function ($val) { return "'".$val."'"; }, self::getValues());

        return 'ENUM('.implode(', ', $values).')';
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform)
    {
        if ($value !== null && !in_array($value, self::getValues())) {
            throw new \InvalidArgumentException("Invalid '".$this->name."' value - expected ".implode(', ', $this->getValues()).', got "'.$value.'".');
        }

        return $value;
    }

    public function getName()
    {
        return $this->name;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
