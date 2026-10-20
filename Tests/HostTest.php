<?php

namespace Omnisearch\Typesense\Tests;

use Omnisearch\Mapping\AttributeLoader;
use Omnisearch\Typesense\Bridge\Symfony\TypesenseHost;
use Omnisearch\Typesense\Mapping;
use Omnisearch\Typesense\Tests\Fixtures\Post;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Typesense\Bundle\TypesenseBundle;

final class HostTest extends TestCase
{
    public function testTheIndexesBecomeTheBundlesMappings(): void
    {
        $builder = new ContainerBuilder();
        $builder->setParameter('kernel.bundles', []);
        self::assertFalse(TypesenseHost::hosts($builder), 'without the bundle, nothing to host');

        $builder->setParameter('kernel.bundles', ['TypesenseBundle' => TypesenseBundle::class]);
        self::assertTrue(TypesenseHost::hosts($builder));

        TypesenseHost::prepend($builder, 'search', ['connection' => 'main'], [AttributeLoader::load(Post::class)]);
        self::assertSame([['mappings' => ['post' => Mapping::toTypesense(AttributeLoader::load(Post::class), 'main')]]], $builder->getExtensionConfig('typesense'), 'on the bundle\'s connection the options name');
    }
}
