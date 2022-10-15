<?php declare(strict_types=1);

namespace Composite\DB\Entity;

use Composite\DB\AbstractEntity;
use Composite\DB\Entity\Attributes;
use Composite\DB\Entity\Columns;
use Composite\DB\Exceptions\EntityException;

class ColumnBuilder
{
    private const PRIMITIVE_COLUMN_MAP = [
        'array' => Columns\ArrayColumn::class,
        'bool' => Columns\BoolColumn::class,
        'float' => Columns\FloatColumn::class,
        'int' => Columns\IntegerColumn::class,
        'string' => Columns\StringColumn::class,
        \stdClass::class => Columns\ObjectColumn::class,
        \DateTime::class => Columns\DateTimeColumn::class,
        \DateTimeImmutable::class => Columns\DateTimeColumn::class,
    ];

    /**
     * @return Columns\AbstractColumn[]
     * @throws EntityException
     */
    public static function fromReflection(\ReflectionClass $reflectionClass): array
    {
        $result = $constructorColumns = $constructorDefaultValues = [];
        foreach ($reflectionClass->getConstructor()->getParameters() as $reflectionParameter) {
            $constructorColumns[$reflectionParameter->getName()] = true;
            if ($reflectionParameter->isPromoted() && $reflectionParameter->isDefaultValueAvailable()) {
                $constructorDefaultValues[$reflectionParameter->getName()] = $reflectionParameter->getDefaultValue();
            }
        }

        foreach ($reflectionClass->getProperties() as $property) {
            if ($property->isStatic() || $property->isPrivate()) {
                continue;
            }
            $type = $property->getType();
            if (!$type instanceof \ReflectionNamedType) {
                throw new EntityException("Property `{$property->name}` must have named type");
            }
            $typeName = $type->getName();
            /** @var Columns\AbstractColumn $columnClass */
            $columnClass = self::PRIMITIVE_COLUMN_MAP[$typeName] ?? null;

            if (!$columnClass && class_exists($typeName)) {
                if (is_subclass_of($typeName, AbstractEntity::class)) {
                    $columnClass = Columns\EntityColumn::class;
                } elseif (is_subclass_of($typeName, \BackedEnum::class)) {
                    $columnClass = Columns\BackedEnumColumn::class;
                } elseif (is_subclass_of($typeName, \UnitEnum::class)) {
                    $columnClass = Columns\UnitEnumColumn::class;
                } else {
                    if (in_array(CastableInterface::class, class_implements($typeName))) {
                        $columnClass = Columns\CastableColumn::class;
                    }
                }
            }

            if (!$columnClass) {
                throw new EntityException("Type `{$property->getType()}` is not supported");
            }

            if (isset($constructorDefaultValues[$property->name])) {
                $hasDefaultValue = true;
                $defaultValue = $constructorDefaultValues[$property->name];
            } elseif ($property->hasDefaultValue()) {
                $hasDefaultValue = true;
                $defaultValue = $property->getDefaultValue();
            } else {
                $hasDefaultValue = false;
                $defaultValue = null;
            }
            $strict = false;
            $primaryKey = null;
            foreach ($property->getAttributes() as $reflectionAttribute) {
                if ($reflectionAttribute->getName() === Attributes\PrimaryKey::class) {
                    $primaryKey = $reflectionAttribute->newInstance();
                } elseif ($reflectionAttribute->getName() === Attributes\Strict::class) {
                    $strict = true;
                }
            }
            $result[$property->getName()] = new $columnClass(
                name: $property->getName(),
                type: $typeName,
                hasDefaultValue: $hasDefaultValue,
                defaultValue: $defaultValue,
                isNullable: $type->allowsNull(),
                isReadOnly: $property->isReadOnly(),
                isConstructorPromoted: !empty($constructorColumns[$property->getName()]),
                isStrict: $strict,
                primaryKey: $primaryKey,
            );
        }
        return $result;
    }
}