<?php

namespace Omnisearch\Typesense\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Omnisearch\ORM\Finder;
use Omnisearch\ORM\Manager;
use Omnisearch\ORM\Query;
use Omnisearch\Typesense\Tests\Application\Kernel;
use Omnisearch\Typesense\Tests\Fixtures\Label;
use Omnisearch\Typesense\Tests\Fixtures\Post;
use Omnisearch\Typesense\TypesenseDriver;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\ApplicationTester;
use Typesense\Bundle\ORM\TypesenseManager;

/**
 * omnisearch hosted by glitchr/typesense-bundle in a Symfony application:
 * the index of omnisearch's attributes is one of the bundle's collections
 * (typesense:create makes it, the bundle's listener indexes it on flush),
 * omnisearch searches through it and loads the entities back; omnisearch's
 * own Doctrine listener is not registered for that connection.
 */
final class HostedTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public static function setUpBeforeClass(): void
    {
        $_SERVER['TYPESENSE_PREFIX'] = 'hosted_'.bin2hex(random_bytes(3)).'_';
        $_SERVER['TYPESENSE_URL'] ??= getenv('TYPESENSE_URL') ?: 'http://localhost:8108';
        $_SERVER['TYPESENSE_KEY'] ??= getenv('TYPESENSE_KEY') ?: 'xyz';
    }

    public function testTheBundleHostsTheIndexAndOmnisearchSearchesThroughIt(): void
    {
        if (!class_exists(Kernel::class) || !class_exists(\Symfony\Bundle\FrameworkBundle\FrameworkBundle::class) || !class_exists(\Doctrine\Bundle\DoctrineBundle\DoctrineBundle::class)) {
            self::markTestSkipped('framework-bundle and doctrine-bundle are needed.');
        }
        self::bootKernel();
        $container = self::getContainer();
        $bundle = $container->get(TypesenseManager::class);
        try {
            if (!$bundle->getConnection()->getHealth()) {
                self::markTestSkipped('Typesense is not healthy.');
            }
        } catch (\Exception $e) {
            self::markTestSkipped('No reachable Typesense server: '.$e->getMessage());
        }

        self::assertEqualsCanonicalizing(['label', 'post'], array_keys($bundle->getCollections()), 'the attributes\' index is a collection of the bundle, beside its configuration\'s');
        self::assertSame(['default'], $container->getParameter('omnisearch.hosted_connections'));
        self::assertFalse($container->has('Omnisearch\ORM\Indexer'), 'the bundle indexes; omnisearch does not twice');

        $manager = $container->get(Manager::class);
        $driver = $manager->connection('post')->driver();
        self::assertInstanceOf(TypesenseDriver::class, $driver);
        self::assertSame($bundle, $driver->manager(), 'the bundle\'s own manager');
        self::assertSame(TypesenseDriver::schemaOf($bundle->getCollection('post')), $driver->schema($manager->classMetadata('post')));

        $entityManager = $container->get(EntityManagerInterface::class);
        (new SchemaTool($entityManager))->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
        $console = new ApplicationTester($application = new Application(self::$kernel));
        $application->setAutoExit(false);
        try {
            self::assertSame(0, $console->run(['command' => 'typesense:create']), $console->getDisplay());
            $entityManager->persist(new Post('Mount Fuji', new \DateTime('2026-01-01'), 'publish', 2, new Label('voyages')));
            $entityManager->persist(new Post('Fujisan in music', new \DateTime('2026-03-01'), 'publish', 1, new Label('en-musique')));
            $entityManager->persist(new Post('Draft about Fuji', new \DateTime('2026-04-01'), 'draft', 3));
            $entityManager->flush();
            $entityManager->clear();

            /** @var Finder $finder */
            $finder = $container->get('omnisearch.finder.post');
            $result = $finder->search((new Query('fuji'))->in('title')->where('state', 'publish')->sortBy('publishedAt', 'desc')->facet('tags', 'audio')->cacheable());
            self::assertTrue($result->ok(), (string) $result->error());
            self::assertSame(['Fujisan in music', 'Mount Fuji'], array_map(fn (Post $p) => $p->getTitle(), $result->objects()));
            self::assertSame(['Fuji'], $result->hit($result->objects()[0])->matchedTokens(), 'what Typesense matched: the prefix');
            self::assertSame(['en-musique', 'voyages'], array_keys($result->facets([], true)['tags']['counts']));
            self::assertSame('tags', $result->facets()['tags']['extra']['field_name'], 'what else Typesense says of the facet');
            self::assertSame(2, $bundle->getFinder('label')->raw(new \Typesense\Bundle\ORM\Query\Query('slug', '*'))->getFound(), 'the bundle\'s own collection beside');

            self::assertSame(0, $console->run(['command' => 'omnisearch:schema', 'index' => ['post']]));
            self::assertSame($driver->schema($manager->classMetadata('post'))['name'], json_decode($console->getDisplay(), true)['post']['name']);
        } finally {
            foreach ($bundle->getCollections() as $collection) {
                $collection->delete();
            }
        }
    }
}
