<?php

namespace Omnisearch\Typesense\Tests\Fixtures;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;

/** Doctrine on an in-memory SQLite database, mapping these fixtures. */
final class EntityManagers
{
    /** @param string|null $directory the entities' directory: these fixtures by default */
    public static function sqlite(?string $directory = null): EntityManager
    {
        $configuration = ORMSetup::createAttributeMetadataConfig([$directory ?? __DIR__], true);
        if (\PHP_VERSION_ID >= 80400 && method_exists($configuration, 'enableNativeLazyObjects')) {
            $configuration->enableNativeLazyObjects(true);
        }
        $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $configuration), $configuration);
        (new SchemaTool($entityManager))->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        return $entityManager;
    }
}
