<?php

namespace Omnisearch\Typesense;

use Omnisearch\Mapping\ClassMetadata;
use Omnisearch\Mapping\FieldMetadata;
use Omnisearch\Mapping\Type;

/**
 * An index's metadata as glitchr/typesense-bundle describes a collection:
 * the very array an entry of its `typesense.mappings` is once its
 * configuration tree has filled the defaults - so the bundle makes the
 * same TypesenseMetadata, the same schema and the same documents as if its
 * configuration had declared the collection.
 *
 * Omnisearch's types become Typesense's (int: int32, bigint: int64, the
 * lists alike; datetime stays the bundle's datetime, a timestamp in int64).
 * A field's sort, optional and locale, and its options under "typesense"
 * (or flat), are the bundle's field options, sent with the schema; the
 * index's options are the collection's default_sorting_field,
 * token_separators and symbols_to_index.
 */
final class Mapping
{
    public const TYPES = [
        Type::STRING => 'string',
        Type::STRINGS => 'string[]',
        Type::INT => 'int32',
        Type::INTS => 'int32[]',
        Type::BIGINT => 'int64',
        Type::FLOAT => 'float',
        Type::FLOATS => 'float[]',
        Type::BOOL => 'bool',
        Type::BOOLS => 'bool[]',
        Type::DATETIME => 'datetime',
        Type::GEOPOINT => 'geopoint',
    ];

    /** The bundle's defaults (its configuration tree's). */
    public const TOKEN_SEPARATORS = ['+', '-', '@', '.', ' '];
    public const SYMBOLS_TO_INDEX = ['+'];

    /**
     * @param string $connection the bundle's connection the collection lives on
     *
     * @return array<string, mixed>
     */
    public static function toTypesense(ClassMetadata $metadata, string $connection = 'default'): array
    {
        $options = $metadata->options['typesense'] ?? $metadata->options;

        return [
            'connection' => $connection,
            'class' => $metadata->class,
            'fields' => array_map(self::field(...), $metadata->fields),
            'default_sorting_field' => $options['default_sorting_field'] ?? null,
            'token_separators' => $options['token_separators'] ?? self::TOKEN_SEPARATORS,
            'symbols_to_index' => $options['symbols_to_index'] ?? self::SYMBOLS_TO_INDEX,
        ];
    }

    /** @return array<string, mixed> */
    public static function field(FieldMetadata $field): array
    {
        $mapping = ['type' => self::TYPES[$field->type] ?? $field->type];
        if (null !== $field->property) {
            $mapping['property'] = $field->property;
        }
        if ($field->facet) {
            $mapping['facet'] = true;
        }
        if ($field->infix) {
            $mapping['infix'] = true;
        }
        $options = $field->options['typesense'] ?? array_diff_key($field->options, array_flip(['typesense']));
        if ($field->sort) {
            $options['sort'] = true;
        }
        if ($field->optional) {
            $options['optional'] = true;
        }
        if (null !== $field->locale) {
            $options['locale'] = $field->locale;
        }
        $mapping['options'] = $options;

        return $mapping;
    }
}
