<?php

namespace Omnisearch\Typesense\Tests;

use Doctrine\ORM\EntityManager;
use Omnisearch\DBAL\DriverFactory;
use Omnisearch\Testing\DriverContractTests;
use Omnisearch\Typesense\Tests\Contract\ContractNote;
use Omnisearch\Typesense\Tests\Fixtures\EntityManagers;
use Omnisearch\Typesense\TypesenseDriverFactory;
use PHPUnit\Framework\TestCase;

/**
 * omnisearch's conformance suite on a Typesense server - beside the
 * harness's container (core/docker: `docker compose run --rm omnisearch test`)
 * or any server at TYPESENSE_URL (default http://localhost:8108, key
 * TYPESENSE_KEY or "xyz"); the tests that need one are skipped when none
 * answers, the others (OFFLINE) run anyway. Each test works in a
 * collection of its own (a random prefix), dropped afterwards.
 */
final class TypesenseContractTest extends TestCase
{
    use DriverContractTests;

    private static ?EntityManager $entityManager = null;
    private string $prefix = '';

    /** What runs without a server: the factory, the options, the mapping, an engine that does not answer. */
    private const OFFLINE = ['testTheFactoryNamesItsEngineAndTheRegistryConnectsIt', 'testARequiredOptionLeftOutIsAConfigurationError', 'testTheContractsIndexIsWhatTheSuiteExpects', 'testAnEngineThatDoesNotAnswerAnswersAStatus'];

    protected function setUp(): void
    {
        if (\in_array($this->name(), self::OFFLINE, true)) {
            return;
        }
        $health = @file_get_contents(self::url().'/health', false, stream_context_create(['http' => ['timeout' => 2]]));
        if (!\is_string($health) || !str_contains($health, '"ok":true')) {
            self::markTestSkipped(\sprintf('No healthy Typesense server at %s: run beside one (core/docker compose).', self::url()));
        }
    }

    private static function url(): string
    {
        return rtrim((string) (getenv('TYPESENSE_URL') ?: 'http://localhost:8108'), '/');
    }

    private static function entityManager(): EntityManager
    {
        return self::$entityManager ??= EntityManagers::sqlite(__DIR__.'/Contract');
    }

    protected function factory(): DriverFactory
    {
        return new TypesenseDriverFactory(null, self::entityManager());
    }

    protected function options(): array
    {
        $this->prefix = '' !== $this->prefix ? $this->prefix : 'contract_'.bin2hex(random_bytes(3)).'_';

        return ['url' => self::url(), 'secret' => getenv('TYPESENSE_KEY') ?: 'xyz', 'prefix' => $this->prefix, 'options' => ['connection_timeout_seconds' => 2]];
    }

    /** @return list<string> */
    protected function requiredOptions(): array
    {
        return ['secret'];
    }

    /** @return array<string, mixed> */
    protected function unreachableOptions(): array
    {
        return ['url' => 'http://127.0.0.1:9', 'secret' => 'xyz', 'options' => ['connection_timeout_seconds' => 1, 'num_retries' => 0]];
    }

    protected function document(string $id, string $title, int $rank, array $labels): object
    {
        return new ContractNote((int) $id, $title, $rank, $labels);
    }
}
