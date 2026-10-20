<?php

namespace Omnisearch\Typesense\Tests;

use Omnisearch\Mapping\AttributeLoader;
use Omnisearch\Typesense\Mapping;
use Omnisearch\Typesense\Tests\Fixtures\EntityManagers;
use Omnisearch\Typesense\Tests\Fixtures\Post;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;
use Typesense\Bundle\Bridge\Symfony\DependencyInjection\Configuration;
use Typesense\Bundle\ORM\Mapping\TypesenseMetadata;
use Typesense\Bundle\ORM\Transformer\EntityTransformer;

/**
 * An index declared by omnisearch's attributes is the very collection the
 * bundle's configuration declares: the same array once the bundle's
 * configuration tree has filled its defaults, so the same metadata and the
 * same schema.
 */
final class MappingTest extends TestCase
{
    /** What config/packages/typesense.yaml would say of Post. */
    private const CONFIGURATION = ['mappings' => ['post' => [
        'connection' => 'default',
        'class' => Post::class,
        'fields' => [
            'state' => ['property' => 'state', 'type' => 'string', 'facet' => true],
            'title' => ['property' => 'title', 'type' => 'string[]', 'infix' => true],
            'tags' => ['property' => 'tags.slug', 'type' => 'string[]', 'infix' => true, 'facet' => true],
            'publishedAt' => ['type' => 'datetime'],
            'audio' => ['property' => 'isAudible', 'type' => 'bool', 'facet' => true],
            'rank' => ['type' => 'int32', 'options' => ['optional' => true, 'sort' => true]],
        ],
    ]]];

    public function testTheIndexIsTheCollectionTheConfigurationWouldDeclare(): void
    {
        if (!class_exists(Processor::class)) {
            self::markTestSkipped('symfony/config is not installed.');
        }
        $configured = (new Processor())->processConfiguration(new Configuration(), [self::CONFIGURATION])['mappings']['post'];

        self::assertEquals($configured, Mapping::toTypesense(AttributeLoader::load(Post::class)));

        $transformer = new EntityTransformer(EntityManagers::sqlite());
        $schema = fn (array $mapping) => array_map(fn ($f) => $f->toArray(), (new TypesenseMetadata('post', $mapping, $transformer))->getConfiguration()['fields']);
        self::assertSame($schema($configured), $schema(Mapping::toTypesense(AttributeLoader::load(Post::class))));
    }

    public function testTheTypes(): void
    {
        self::assertSame(['string', 'string[]', 'int32', 'int32[]', 'int64', 'float', 'float[]', 'bool', 'bool[]', 'datetime', 'geopoint'], array_values(Mapping::TYPES));
    }
}
