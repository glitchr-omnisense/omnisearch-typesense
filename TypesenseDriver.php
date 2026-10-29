<?php

namespace Omnisearch\Typesense;

use Omnisearch\DBAL\Driver;
use Omnisearch\DBAL\Facet;
use Omnisearch\DBAL\Filter;
use Omnisearch\DBAL\Hit;
use Omnisearch\DBAL\Request;
use Omnisearch\DBAL\Response;
use Omnisearch\Exception\DriverException;
use Omnisearch\Mapping\ClassMetadata;
use Psr\Http\Client\ClientExceptionInterface;
use Typesense\Bundle\Exception\TypesenseException;
use Typesense\Bundle\ORM\Mapping\TypesenseCollection;
use Typesense\Bundle\ORM\Mapping\TypesenseMetadata;
use Typesense\Bundle\ORM\Query\Query;
use Typesense\Bundle\ORM\Query\Response as TypesenseResponse;
use Typesense\Bundle\ORM\Transformer\Abstract\TransformerInterface;
use Typesense\Bundle\ORM\TypesenseFinder;
use Typesense\Bundle\ORM\TypesenseManager;
use Typesense\Exceptions\ObjectNotFound;
use Typesense\Exceptions\TypesenseClientError;

/**
 * Omnisearch's driver for Typesense, over glitchr/typesense-bundle: an
 * index is one of the bundle's collections (its discriminator
 * sub-collections with it), an object becomes its document by the
 * bundle's transformer, a Request the bundle's Query, the bundle's
 * Response a Response. Nothing of the bundle is written again here.
 *
 * In a Symfony application with the bundle, the collections are the
 * bundle's own (TypesenseHost declared them); elsewhere they are made here
 * on first use from the metadata.
 *
 * A server that cannot be reached is caught by the PSR-18 interfaces
 * (Psr\Http\Client\ClientExceptionInterface), whatever client php-http's
 * discovery gave typesense-php: Symfony's Psr18Client throws its
 * Psr18NetworkException, which the bundle (catching HTTPlug's exception)
 * lets through. A search then answers a failed Response (503), a write
 * throws a DriverException (503).
 */
final class TypesenseDriver implements Driver
{
    public function __construct(
        private readonly TypesenseManager $manager,
        private readonly string $connection,
        private readonly ?TransformerInterface $transformer = null,
        private readonly string $prefix = '',
    ) {
    }

    public function manager(): TypesenseManager
    {
        return $this->manager;
    }

    /** The bundle's collection of an index, made and registered when the bundle has none. */
    public function collection(ClassMetadata $metadata): TypesenseCollection
    {
        $collections = $this->manager->getCollections();
        if (isset($collections[$metadata->index])) {
            return $collections[$metadata->index];
        }
        if (null === $this->transformer) {
            throw new \LogicException(\sprintf('The bundle has no "%s" collection and this driver no transformer to make one with.', $metadata->index));
        }
        $collection = new TypesenseCollection(new TypesenseMetadata($metadata->index, Mapping::toTypesense($metadata, $this->connection), $this->transformer, $this->prefix), $this->manager->getConnection($this->connection));
        $this->manager->addCollection($collection);
        $this->manager->addFinder(new TypesenseFinder($collection));

        return $collection;
    }

    /** @return array<string, TypesenseCollection> the index's collection and its sub-collections, by name */
    public function collections(ClassMetadata $metadata): array
    {
        $root = $this->collection($metadata);

        return array_filter($this->manager->getCollections(), fn (TypesenseCollection $c, string $name) => $c === $root || str_starts_with($name, $metadata->index.'__'), ARRAY_FILTER_USE_BOTH);
    }

    public function create(ClassMetadata $metadata): void
    {
        self::write(function () use ($metadata): void {
            foreach ($this->collections($metadata) as $collection) {
                $collection->create();
            }
        });
    }

    public function drop(ClassMetadata $metadata): void
    {
        self::write(function () use ($metadata): void {
            foreach ($this->collections($metadata) as $collection) {
                $collection->delete();
            }
        });
    }

    public function schema(ClassMetadata $metadata): array
    {
        return self::schemaOf($this->collection($metadata));
    }

    /** @return array<string, array<string, mixed>> the schemas of the index's collection and sub-collections, by name on the server */
    public function schemas(ClassMetadata $metadata): array
    {
        $schemas = [];
        foreach ($this->collections($metadata) as $collection) {
            $schema = self::schemaOf($collection);
            $schemas[$schema['name']] = $schema;
        }

        return $schemas;
    }

    /** What TypesenseCollection::create() sends. */
    public static function schemaOf(TypesenseCollection $collection): array
    {
        $schema = $collection->metadata()->getConfiguration();
        $schema['fields'] = array_values(array_map(fn ($f) => $f->toArray(), $schema['fields']));
        foreach ($schema['fields'] as &$field) {
            $field['type'] = $collection->transformer()->cast($field['type']);
        }

        return $schema;
    }

    public function index(ClassMetadata $metadata, object ...$objects): void
    {
        self::write(function () use ($metadata, $objects): void {
            foreach ($objects as $object) {
                foreach ($this->collections($metadata) as $collection) {
                    if ($collection->supports($object)) {
                        $collection->documents()->upsert($collection->transformer()->convert($object), []);
                    }
                }
            }
        });
        $this->clearCache($metadata);
    }

    public function remove(ClassMetadata $metadata, string ...$ids): void
    {
        foreach ($this->collections($metadata) as $collection) {
            foreach ($ids as $id) {
                try {
                    $collection->documents()->delete($id);
                } catch (ObjectNotFound) {
                } catch (ClientExceptionInterface $e) {
                    throw self::failure($e);
                } catch (TypesenseException $e) {
                    // The bundle's word for a document not there, as for a server not answering: only the status tells.
                    if (404 !== (int) $e->getCode()) {
                        throw self::failure($e);
                    }
                }
            }
        }
        $this->clearCache($metadata);
    }

    public function search(ClassMetadata $metadata, Request $request): Response
    {
        $this->collection($metadata);
        $finder = $this->manager->getFinder($metadata->index);
        try {
            $response = $finder->raw(self::query($metadata, $request), $request->cacheable);
        } catch (ClientExceptionInterface $e) {
            return Response::failed(503, 'Typesense not reached: '.$e->getMessage());
        }

        return self::response($response);
    }

    /** A Request as the bundle's Query. */
    public static function query(ClassMetadata $metadata, Request $request): Query
    {
        $fields = $request->fields ?: array_keys(array_filter($metadata->fields, fn ($f) => \in_array($f->type, ['string', 'string[]'], true)));
        $query = new Query(implode(', ', $fields), $request->term ?? '');

        if (null !== ($infix = $request->option('infix'))) {
            $query->infix($infix);
        }
        if (null !== $request->perPage) {
            $query->perPage($request->perPage);
        }
        $query->page($request->page);
        if ($request->sort) {
            $query->sortBy(implode(',', array_map(fn ($s) => $s[0].':'.$s[1], $request->sort)));
        }
        if ($request->facets) {
            $query->facetBy(implode(',', $request->facets));
        }
        foreach ($request->filters as $filter) {
            $query->addFilterBy(self::filter($filter));
        }
        if (null !== $request->maxFacetValues) {
            $query->maxFacetValues($request->maxFacetValues);
        }
        foreach ($request->options as $name => $value) {
            if ('infix' !== $name) {
                $query->addHeader($name, $value);
            }
        }

        return $query;
    }

    /** A Filter in Typesense's filter_by syntax. */
    public static function filter(Filter $filter): string
    {
        $value = fn ($v) => match (true) {
            \is_bool($v) => $v ? 'true' : 'false',
            \is_int($v), \is_float($v) => (string) $v,
            default => '`'.str_replace('`', '', (string) $v).'`',
        };

        return match ($filter->operator) {
            Filter::EQUALS => $filter->field.':='.$value($filter->value),
            Filter::NOT_EQUALS => $filter->field.':!='.$value($filter->value),
            Filter::IN => $filter->field.':=['.implode(',', array_map($value, $filter->value)).']',
            Filter::NOT_IN => $filter->field.':!=['.implode(',', array_map($value, $filter->value)).']',
            default => $filter->field.':'.$filter->operator.$value($filter->value),
        };
    }

    /** The bundle's Response as a Response. */
    public static function response(TypesenseResponse $response): Response
    {
        if (200 !== $response->getStatus()) {
            // The bundle's finder keeps Typesense's message to itself outside debug: say at least what the status means.
            $error = trim((string) $response->getContent());

            return Response::failed($response->getStatus(), '' !== $error ? $error : \sprintf('Typesense answered HTTP %d%s.', $response->getStatus(), match (true) {
                0 === $response->getStatus() || $response->getStatus() >= 500 => ': not reached, or not ready',
                404 === $response->getStatus() => ': no such collection',
                default => '',
            }));
        }

        $hits = [];
        foreach ($response->getRawResults() ?? [] as $hit) {
            $highlights = [];
            foreach ($hit['highlights'] ?? [] as $highlight) {
                $highlights[$highlight['field']] = $highlight['matched_tokens'] ?? [];
            }
            $hits[] = new Hit((string) ($hit['document']['id'] ?? ''), $hit['document'] ?? [], $highlights, isset($hit['text_match']) ? (float) $hit['text_match'] : null, $hit);
        }

        $facets = [];
        foreach ($response->getFacetCounts() as $facet) {
            // getFacetCounts() marks every value unchecked: the checking is the Result's.
            $values = array_map(fn (array $count) => array_diff_key($count, ['checked' => 1]), array_values($facet['counts'] ?? []));
            $facets[$facet['field_name']] = new Facet($facet['field_name'], $values, array_diff_key($facet, ['counts' => 1]));
        }

        return new Response((int) $response->getFound(), (int) ($response->getPage() ?? 1), $hits, $facets);
    }

    /**
     * A write that Typesense refuses, or that cannot reach it, is a
     * DriverException: the family's, its code Typesense's HTTP status (503
     * when there was none), the bundle's or the client's exception before it
     * (a PSR-18 client's own included).
     */
    private static function write(\Closure $write): void
    {
        try {
            $write();
        } catch (TypesenseException|TypesenseClientError|ClientExceptionInterface $e) {
            throw self::failure($e);
        }
    }

    private static function failure(\Throwable $e): DriverException
    {
        $status = (int) $e->getCode();

        return new DriverException('Typesense: '.$e->getMessage(), $status >= 400 && $status < 600 ? $status : 503, $e);
    }

    private function clearCache(ClassMetadata $metadata): void
    {
        try {
            $this->manager->getFinder($metadata->index)?->cache()->clear();
        } catch (\Throwable) {
        }
    }
}
