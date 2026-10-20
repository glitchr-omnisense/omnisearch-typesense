<?php

namespace Omnisearch\Typesense\Bridge\Symfony;

use Omnisearch\Bridge\Symfony\Host;
use Omnisearch\Typesense\Mapping;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Typesense\Bundle\TypesenseBundle;

/**
 * In a Symfony application with glitchr/typesense-bundle, the indexes a
 * "typesense" connection's classes declare by omnisearch's attributes
 * become the bundle's mappings, prepended to its configuration: the bundle
 * creates the collections (typesense:create), indexes them on Doctrine's
 * events and imports them (typesense:action, typesense:update), exactly as
 * for those its configuration declares; omnisearch searches through it.
 */
final class TypesenseHost implements Host
{
    public static function hosts(ContainerBuilder $builder): bool
    {
        return \in_array(TypesenseBundle::class, $builder->hasParameter('kernel.bundles') ? $builder->getParameter('kernel.bundles') : [], true);
    }

    public static function prepend(ContainerBuilder $builder, string $connection, array $options, array $metadata): void
    {
        $mappings = [];
        foreach ($metadata as $classMetadata) {
            $mappings[$classMetadata->index] = Mapping::toTypesense($classMetadata, (string) ($options['connection'] ?? 'default'));
        }
        if ($mappings) {
            $builder->prependExtensionConfig('typesense', ['mappings' => $mappings]);
        }
    }
}
