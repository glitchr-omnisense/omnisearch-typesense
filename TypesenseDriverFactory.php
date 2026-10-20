<?php

namespace Omnisearch\Typesense;

use Doctrine\Persistence\ObjectManager;
use Omnisearch\DBAL\Driver;
use Omnisearch\DBAL\DriverFactory;
use Omnisearch\Exception\InvalidConfigException;
use Typesense\Bundle\DBAL\Connection;
use Typesense\Bundle\ORM\Transformer\EntityTransformer;
use Typesense\Bundle\ORM\TypesenseManager;

/**
 * The "typesense" engine. Two ways:
 *
 * - over the bundle's manager (a Symfony application with
 *   glitchr/typesense-bundle: autowired), the connection named by the
 *   `connection` option (default "default"), its collections the bundle's;
 * - on its own, from the options `url` (or scheme, host, port), `secret`,
 *   `prefix` (put in front of every collection's name on the server) and
 *   `options` (the client's): a manager of its own, the collections made
 *   from the metadata, documents made by the bundle's transformer on
 *   Doctrine's $objectManager.
 */
final class TypesenseDriverFactory implements DriverFactory
{
    public function __construct(
        private readonly ?TypesenseManager $manager = null,
        private readonly ?ObjectManager $objectManager = null,
    ) {
    }

    public function name(): string
    {
        return 'typesense';
    }

    public function create(array $options): Driver
    {
        $connection = (string) ($options['connection'] ?? 'default');
        if ($this->manager && isset($this->manager->getConnections()[$connection])) {
            return new TypesenseDriver($this->manager, $connection, $this->transformer(), (string) ($options['prefix'] ?? ''));
        }

        if (empty($options['secret'])) {
            throw new InvalidConfigException('A Typesense connection of its own needs the option "secret" (and "url", or "host").');
        }
        $manager = new TypesenseManager($connection);
        $manager->addConnection(new Connection($connection, array_intersect_key($options, array_flip(['secret', 'url', 'scheme', 'host', 'port', 'path', 'options']))));

        return new TypesenseDriver($manager, $connection, $this->transformer() ?? throw new InvalidConfigException('A Typesense connection of its own needs Doctrine\'s object manager (the factory\'s second argument).'), (string) ($options['prefix'] ?? ''));
    }

    private function transformer(): ?EntityTransformer
    {
        return $this->objectManager ? new EntityTransformer($this->objectManager) : null;
    }
}
