<?php

namespace Omnisearch\Typesense\Tests;

use Omnisearch\DBAL\Request;
use Omnisearch\Exception\DriverException;
use Omnisearch\Mapping\AttributeLoader;
use Omnisearch\Typesense\Tests\Contract\ContractNote;
use Omnisearch\Typesense\Tests\Fixtures\EntityManagers;
use Omnisearch\Typesense\TypesenseDriverFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Psr18Client;

/**
 * A server that cannot be reached, through a PSR-18 client whose network
 * failure is a Psr\Http\Client\NetworkExceptionInterface and nothing else -
 * Symfony's Psr18Client (its Psr18NetworkException), the client php-http's
 * discovery gives typesense-php when symfony/http-client is installed, and a
 * bare one: a search answers a failed Response, a write throws the family's
 * DriverException (omnisearch's Driver contract), never the client's own.
 */
final class NetworkFailureTest extends TestCase
{
    /** @return iterable<string, array{\Closure(): ClientInterface}> */
    public static function clients(): iterable
    {
        yield 'symfony/http-client' => [static fn (): ClientInterface => new Psr18Client(new MockHttpClient(static function (string $method, string $url): never {
            throw new TransportException(sprintf('Could not resolve host: typesense.invalid for "%s".', $url));
        }))];
        yield 'a bare PSR-18 client' => [static fn (): ClientInterface => new class implements ClientInterface {
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                throw new class('Connection refused', $request) extends \RuntimeException implements NetworkExceptionInterface {
                    public function __construct(string $message, private readonly RequestInterface $request)
                    {
                        parent::__construct($message);
                    }

                    public function getRequest(): RequestInterface
                    {
                        return $this->request;
                    }
                };
            }
        }];
    }

    #[DataProvider('clients')]
    public function testASearchAnswersAFailedResponse(\Closure $client): void
    {
        [$driver, $metadata] = $this->driver($client());

        $response = $driver->search($metadata, new Request('fuji'));
        self::assertFalse($response->ok());
        self::assertSame(503, $response->status);
        self::assertStringContainsString('Typesense', (string) $response->error);
    }

    #[DataProvider('clients')]
    public function testAWriteThrowsTheFamilysException(\Closure $client): void
    {
        [$driver, $metadata] = $this->driver($client());

        foreach (['index' => fn () => $driver->index($metadata, new ContractNote(1, 'Mount Fuji at dawn', 3, ['travel'])), 'remove' => fn () => $driver->remove($metadata, '1'), 'create' => fn () => $driver->create($metadata), 'drop' => fn () => $driver->drop($metadata)] as $write => $call) {
            try {
                $call();
                self::fail($write.': no exception');
            } catch (DriverException $e) {
                self::assertSame(503, $e->getCode(), $write);
                self::assertInstanceOf(NetworkExceptionInterface::class, $e->getPrevious(), $write);
            }
        }
    }

    /** @return array{\Omnisearch\DBAL\Driver, \Omnisearch\Mapping\ClassMetadata} */
    private function driver(ClientInterface $client): array
    {
        $driver = (new TypesenseDriverFactory(null, EntityManagers::sqlite(__DIR__.'/Contract')))->create([
            'url' => 'http://typesense.invalid:8108',
            'secret' => 'xyz',
            'options' => ['client' => $client, 'num_retries' => 0],
        ]);

        return [$driver, AttributeLoader::load(ContractNote::class)];
    }
}
