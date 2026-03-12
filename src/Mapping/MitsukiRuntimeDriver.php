<?php

declare(strict_types=1);

namespace Mitsuki\ORM\Mapping;

use Doctrine\ORM\Mapping\ClassMetadata as ORMMetadata;
use Doctrine\Persistence\Mapping\ClassMetadata as PersistenceMetadata;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use Mitsuki\ORM\Attributes\{Entity, ManyToOne, OneToOne, ManyToMany};
use ReflectionNamedType;

/**
 * MitsukiRuntimeDriver
 * * A custom Doctrine mapping driver that configures entity metadata using PHP 8 Attributes 
 * and Reflection. This driver eliminates the need for XML, YAML, or DocBlock annotations.
 * * Key Features:
 * - Automatic table and column naming (CamelCase to snake_case).
 * - Support for Mitsuki-specific relationship attributes (OneToOne, ManyToOne, ManyToMany).
 * - Intelligent column type guessing based on native PHP types.
 * - Cascade and nullability management for relationships.
 * * 
 * * @package Mitsuki\ORM\Mapping
 * @author Zgenius matondo <zgeniuscoders@gmail.com>
 */
class MitsukiRuntimeDriver implements MappingDriver
{
    /**
     * Loads metadata for a specific class into Doctrine's ClassMetadata instance.
     * * This method analyzes the class attributes and its properties to define:
     * - Primary table structure.
     * - Identifiers (Primary Keys) and auto-increment strategies.
     * - Simple field mappings with nullability support.
     * - Complex relational mappings using Mitsuki Attributes.
     *
     * @param string $className The fully qualified class name.
     * @param PersistenceMetadata|ORMMetadata $metadata The metadata object to populate.
     * @return void
     * @throws \ReflectionException If the class cannot be reflected.
     */
    public function loadMetadataForClass($className, PersistenceMetadata $metadata): void
    {
        if (!$metadata instanceof ORMMetadata) return;

        $reflection = $metadata->getReflectionClass();
        $attributes = $reflection->getAttributes(Entity::class);
        if (empty($attributes)) return;

        $entityAttr = $attributes[0]->newInstance();
        $tableName = $entityAttr->table ?? $this->camelToSnake($reflection->getShortName());
        $metadata->setPrimaryTable(['name' => $tableName]);

        foreach ($reflection->getProperties() as $property) {
            $fieldName = $property->getName();
            $type = $property->getType();
            $propertyAttributes = $property->getAttributes();

            foreach ($propertyAttributes as $attr) {
                $instance = $attr->newInstance();

                if ($instance instanceof ManyToOne) {
                    $metadata->mapManyToOne([
                        'fieldName'    => $fieldName,
                        'targetEntity' => $instance->targetEntity,
                        'joinColumns'  => [['name' => $this->camelToSnake($fieldName) . '_id', 'nullable' => $instance->nullable]],
                        'cascade'      => $instance->cascade
                    ]);
                    continue 2;
                }

                if ($instance instanceof OneToOne) {
                    $metadata->mapOneToOne([
                        'fieldName'    => $fieldName,
                        'targetEntity' => $instance->targetEntity,
                        'joinColumns'  => [['name' => $this->camelToSnake($fieldName) . '_id', 'unique' => true, 'nullable' => $instance->nullable]]
                    ]);
                    continue 2;
                }

                if ($instance instanceof ManyToMany) {
                    $metadata->mapManyToMany([
                        'fieldName'    => $fieldName,
                        'targetEntity' => $instance->targetEntity,
                        'joinTable'    => ['name' => $instance->pivotTable ?? $tableName . '_' . $this->camelToSnake($fieldName)]
                    ]);
                    continue 2;
                }
            }

            if (!$type instanceof ReflectionNamedType) continue;
            $typeName = $type->getName();

            if ($fieldName === 'id') {
                $metadata->mapField(['fieldName' => 'id', 'type' => 'integer', 'id' => true]);
                $metadata->setIdGeneratorType(ORMMetadata::GENERATOR_TYPE_AUTO);
                continue;
            }

            if ($typeName !== 'Doctrine\Common\Collections\Collection') {
                $metadata->mapField([
                    'fieldName' => $fieldName,
                    'type'      => $this->guessType($typeName, $fieldName),
                    'nullable'  => $type->allowsNull(),
                ]);
            }
        }
    }

    /**
     * Attempts to derive the Doctrine mapping type from a native PHP type.
     * * It maps standard types (int, bool, float) and handles specific conventions, 
     * such as mapping fields ending in '_at' to 'datetime_immutable'.
     *
     * @param string $phpType The name of the PHP type (e.g., 'int', 'string').
     * @param string $fieldName The name of the property being mapped.
     * @return string The inferred Doctrine mapping type (e.g., 'integer', 'json', 'datetime_immutable').
     */
    private function guessType(string $phpType, string $fieldName): string
    {
        if (str_ends_with($fieldName, '_at')) return 'datetime_immutable';
        return match ($phpType) {
            'int' => 'integer',
            'bool' => 'boolean',
            'float' => 'float',
            'array' => 'json',
            \DateTimeInterface::class => 'datetime_immutable',
            default => 'string',
        };
    }

    /**
     * Converts a string from CamelCase to snake_case.
     * * Used for automatic generation of table names and join column names 
     * when no explicit name is provided in the attributes.
     *
     * @param string $input The string to convert (e.g., "PostCategory").
     * @return string The snake_case version (e.g., "post_category").
     */
    private function camelToSnake(string $input): string
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $input));
    }

    /**
     * Checks whether a class is considered transient (ignored by the ORM).
     * * @param string $className
     * @return bool Always false in this implementation as all classes with #[Entity] are processed.
     */
    public function getAllClassNames(): array
    {
        return [];
    }

    public function isTransient($className): bool
    {
        return false;
    }
}
