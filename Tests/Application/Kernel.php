<?php

namespace Omnisearch\Typesense\Tests\Application;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Omnisearch\Bridge\Symfony\OmnisearchBundle;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Typesense\Bundle\TypesenseBundle;

/**
 * A Symfony application as lesringostars is: glitchr/typesense-bundle for
 * the connection (and a collection of its own configuration), omnisearch
 * for the entities declaring their index by its attributes, Doctrine on
 * SQLite.
 */
final class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        return [new FrameworkBundle(), new DoctrineBundle(), new TypesenseBundle(), new OmnisearchBundle()];
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/omnisearch-typesense-application/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/omnisearch-typesense-application/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $fixtures = \dirname(__DIR__).'/Fixtures';
        $container->services()->set('logger', NullLogger::class);
        $container->extension('framework', ['test' => true, 'secret' => 'test', 'http_method_override' => false, 'handle_all_throwables' => true, 'php_errors' => ['log' => true]]);
        $container->extension('doctrine', [
            'dbal' => ['driver' => 'pdo_sqlite', 'memory' => true],
            'orm' => ['mappings' => ['Fixtures' => ['type' => 'attribute', 'is_bundle' => false, 'dir' => $fixtures, 'prefix' => 'Omnisearch\Typesense\Tests\Fixtures']]],
        ]);
        $container->extension('typesense', [
            'collection_prefix' => '%env(TYPESENSE_PREFIX)%',
            'attributes' => [],
            'connections' => ['default' => ['url' => '%env(TYPESENSE_URL)%', 'secret' => '%env(TYPESENSE_KEY)%', 'options' => ['connection_timeout_seconds' => 2]]],
            'mappings' => ['label' => ['class' => 'Omnisearch\Typesense\Tests\Fixtures\Label', 'fields' => ['slug' => ['type' => 'string']]]],
        ]);
        $container->extension('omnisearch', [
            'connections' => ['default' => ['driver' => 'typesense', 'options' => ['connection' => 'default']]],
            'mappings' => [$fixtures],
        ]);
    }
}
