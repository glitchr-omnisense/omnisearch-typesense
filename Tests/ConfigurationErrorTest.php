<?php

namespace Omnisearch\Typesense\Tests;

use Omnisearch\Exception\InvalidConfigException;
use Omnisearch\Exception\OmnisearchException;
use Omnisearch\Mapping\AttributeLoader;
use Omnisearch\Typesense\Tests\Contract\ContractNote;
use Omnisearch\Typesense\TypesenseDriver;
use PHPUnit\Framework\TestCase;
use Typesense\Bundle\ORM\TypesenseManager;

/** An index the bundle does not know, and no transformer to make it with: the family's configuration error. */
final class ConfigurationErrorTest extends TestCase
{
    public function testAnIndexThatCannotBeMadeIsAConfigurationError(): void
    {
        $driver = new TypesenseDriver(new TypesenseManager('default'), 'default');

        try {
            $driver->collection(AttributeLoader::load(ContractNote::class) ?? throw new \LogicException('no index'));
            self::fail('no exception');
        } catch (OmnisearchException $e) {
            self::assertInstanceOf(InvalidConfigException::class, $e);
            self::assertStringContainsString('"contract_note"', $e->getMessage());
        }
    }
}
