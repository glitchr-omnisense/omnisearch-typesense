# omnisearch/typesense

[Typesense](https://typesense.org) for [omnisense/omnisearch](https://github.com/glitchr-omnisense/omnisearch),
over [glitchr/typesense-bundle](https://github.com/glitchr-studio/typesense-bundle)'s
framework-free part: the indexes declared by omnisearch's attributes become the bundle's
collections, objects its documents, omnisearch's requests its queries, its responses omnisearch's.
Nothing the bundle does is written again here. The calls go through the bundle to the
`typesense/typesense-php` client, on Typesense's REST API at the configured node
(`http://localhost:8108` by default):

| Operation | Typesense API |
|---|---|
| create | `POST /collections`, each collection of the index (one that exists is left) |
| drop | `DELETE /collections/{name}` |
| index | an upsert of each document, one call per document |
| remove | `DELETE /collections/{name}/documents/{id}` |
| search | `GET /collections/{name}/documents/search` |

| Class | |
|---|---|
| `Mapping` | an index's metadata as the bundle's mapping: the very array its `typesense.mappings` configuration gives, so the same collection, schema and documents |
| `TypesenseDriver` | the bundle's `TypesenseManager`, `TypesenseCollection` (an index and its discriminator sub-collections), `EntityTransformer`, `Query` and `Response`: a `Request` becomes the parameters an application wrote with the bundle's setters, a `Filter` Typesense's `filter_by` syntax; `schemas()` gives every collection's |
| `TypesenseDriverFactory` | over the bundle's manager (Symfony, autowired; the `connection` option names the bundle's connection), or on its own from `url`/`secret`/`prefix` with Doctrine's object manager |
| `Bridge\Symfony\TypesenseHost` | with the bundle in a Symfony application, the indexes of a `typesense` connection are prepended to the bundle's configuration: its commands and Doctrine listener serve them |

## Installation

```sh
composer require omnisense/omnisearch omnisearch/typesense
```

Requires PHP 8.2, `omnisense/omnisearch` (1.x) and `glitchr/typesense-bundle` (2.x), which brings
`doctrine/orm`, `typesense/typesense-php` (4 or 5), `php-http/curl-client` and `symfony/cache`. The
objects indexed are Doctrine entities: the bundle's transformer reads their identifier and their
inheritance from Doctrine's metadata. In a Symfony application with
`Omnisearch\Bridge\Symfony\OmnisearchBundle`, the factory is registered as soon as the package is
installed.

## Credentials

One: the Typesense server's **API key** (`secret`). A self-hosted server is started with it (its
`--api-key` flag; the harness uses `xyz`); a Typesense Cloud cluster gives its keys in its
dashboard. The driver writes (creates collections, upserts and deletes documents): it needs a key
allowed to, not a search-only key. No test account is published; the Docker image
`typesense/typesense` runs a server locally.

## Options

`new TypesenseDriverFactory(?TypesenseManager $manager = null, ?ObjectManager $objectManager = null)`,
then a connection's options:

| Option | Type | Default | Required | |
|---|---|---|---|---|
| `connection` | string | `default` | no | with the bundle's manager: the bundle connection to use. When the manager has no connection of that name (or no manager was given), the driver makes its own, under this name |
| `secret` | string | - | for a connection of its own | the API key; without it `create()` is an `InvalidConfigException` |
| `url` | string | - | no | `scheme://host:port`; its parts fill the next options |
| `scheme` | string | `http` | no | |
| `host` | string | `localhost` | no | |
| `port` | int | `8108` | no | |
| `path` | string | `''` | no | kept by the bundle's configuration; the client's node is scheme, host and port |
| `options` | array | `[]` | no | the Typesense PHP client's own options (`connection_timeout_seconds`, `num_retries`...) |
| `prefix` | string | `''` | no | put in front of the server names of the collections this driver makes; the collections of the bundle's own configuration keep the bundle's naming |

A connection of its own also needs the factory's second argument, Doctrine's `ObjectManager`;
without it `create()` is an `InvalidConfigException`. Over the bundle's manager, the collections
and the transformer are the bundle's, and only `connection` and `prefix` are read.

Per field (`#[Field]`) and per index (`#[Index]`), as `Mapping` reads them:

| Where | Read | |
|---|---|---|
| `#[Field]` `type` | always | the table under [Behaviour](#behaviour) |
| `#[Field]` `property` | when set | the bundle's `property` |
| `#[Field]` `facet`, `infix` | when true | the bundle's field flags |
| `#[Field]` `sort`, `optional`, `locale` | when set | added to the field's options (`sort: true`, `optional: true`, `locale: '<code>'`) |
| `#[Field]` `options` | always | `options['typesense']`, or the options themselves without a `typesense` key: the bundle's field options, sent with the schema |
| `#[Index]` `options` | `default_sorting_field`, `token_separators`, `symbols_to_index` | from `options['typesense']`, or the options themselves without that key; defaults `null` (then the bundle's primary identifier), `['+', '-', '@', '.', ' ']`, `['+']` |

Per query (`Query::option()`): `infix` (`off`, `always` or `fallback`, one value or one per searched
field, comma-separated; another value is dropped by the bundle), and any other name, sent as a
Typesense search parameter of that name (`num_typos`, `pinned_hits`, `drop_tokens_threshold`...).

## Capabilities

| omnisearch | Supported |
|---|---|
| `create()`, `drop()` | yes; an entity with Doctrine inheritance has its collection and one sub-collection per discriminator value |
| `schema()` | yes, calls nothing; `schemas()` (this driver's own method) gives every collection's, by server name |
| `index()`, `remove()` | yes |
| term in fields | yes; with no `in()`, every `string` and `string[]` field |
| infix match | on the fields declared `infix: true`, when the query asks (`->option('infix', 'always')`) |
| matched tokens, `Hit::$score` | yes: `highlights[].matched_tokens`, `text_match` |
| every `Filter` operator | yes |
| facets, `maxFacetValues` | yes; a value's `highlighted` and Typesense's other facet keys kept |
| sort, pages | yes |
| `cacheable()` | the bundle finder's cache (an in-memory one when the driver makes its own finder), a successful answer only, cleared after each write of the index |
| multi-search, grouping, vector search | not exposed by omnisearch; Typesense parameters can still be passed by `option()` |

## Behaviour

**The collections.** An index is the bundle's collection of the same name. Over the bundle's
manager, it is the collection the bundle's configuration (or `TypesenseHost`) declared. Otherwise
the driver makes it on first use from `Mapping::toTypesense($metadata)`, with the `prefix`, and
registers it and a finder in its manager. Its sub-collections are the manager's collections named
`<index>__<discriminator value>`.

**The schema.** The bundle adds to the declared fields an `id` (string) and a `primary` field (of
the type of Doctrine's identifier) from the entity's identifier, and, for an entity with
inheritance, the discriminator column as a facet. A field named `id` is refused (the bundle reserves
it). The `default_sorting_field` is the index option, else `primary`: omnisearch's conformance
entity has an integer identifier because Typesense does not sort on a string field by default, so
an entity with a string identifier needs a `default_sorting_field` of its own. Types:

| omnisearch `Type` | Typesense |
|---|---|
| `STRING`, `STRINGS` | `string`, `string[]` |
| `INT`, `INTS` | `int32`, `int32[]` |
| `BIGINT` | `int64` |
| `FLOAT`, `FLOATS` | `float`, `float[]` |
| `BOOL`, `BOOLS` | `bool`, `bool[]` |
| `DATETIME` | the bundle's `datetime`, sent as `int64` (a timestamp) |
| `GEOPOINT` | `geopoint` |

**Writes.** `create()` creates each collection (one that exists is left); `drop()` deletes each.
`index()` upserts each object's document, converted by the bundle's transformer, in every
collection of the index that supports the object's class, one call per document, then clears the
finder's cache. `remove()` deletes each identifier from every collection; a document not there is
no error, any other failure a `DriverException`.

**Search.** The `Request` becomes the bundle's `Query`: `query_by` the fields (or every string
field), `q` the term (`''` when null), `infix`, `per_page` when set, `page`, `sort_by`
(`field:asc,field:desc`), `facet_by`, `filter_by` (each `Filter`, joined by ` && `),
`max_facet_values`, then every other option by its name. Filters:

| `Filter` | `filter_by` |
|---|---|
| `=` | `field:=value` |
| `!=` | `field:!=value` |
| `in` | `field:=[a,b]` |
| `not in` | `field:!=[a,b]` |
| `<`, `<=`, `>`, `>=` | `field:<value`, `field:<=value`... |

A string value is written between backquotes (a backquote in it is dropped), a number as it is, a
boolean as `true` or `false`.

**The answer.** A hit's `id` is its document's `id`, its `document` the document, its `highlights`
each highlighted field's `matched_tokens`, its `score` `text_match`, its `raw` Typesense's hit. A
facet is Typesense's `facet_counts` entry: its `counts` (`value`, `count`, `highlighted`) and the
rest as `extra`. `found` and `page` are Typesense's. A status other than 200 is a failed `Response`:
its message is Typesense's when the bundle kept it (in debug), otherwise `Typesense answered HTTP
<status>: not reached, or not ready.` (0 or 5xx), `Typesense answered HTTP 404: no such
collection.`, or `Typesense answered HTTP <status>.`

## In Symfony, with glitchr/typesense-bundle

```yaml
# config/packages/typesense.yaml: the connection stays the bundle's
typesense:
    connections:
        default: { url: '%env(TYPESENSE_URL)%', secret: '%env(TYPESENSE_SECRET)%' }

# config/packages/omnisearch.yaml
omnisearch:
    connections:
        default: { driver: typesense, options: { connection: default } }
```

The classes declaring an `#[Index]` become the bundle's collections (`TypesenseHost`, when
`Typesense\Bundle\TypesenseBundle` is registered); `bin/console typesense:create` then
`typesense:action upsert` create and fill them, the bundle's Doctrine listener keeps them, and
`Omnisearch\ORM\Finder` searches them. A collection still in the bundle's YAML lives beside them;
declare a collection in one place, not both. Without the bundle registered, the driver makes its
own connection from the options (`url`, `secret`...) and omnisearch's own Doctrine listener indexes.

## Infix

A field declared `infix: true` is created with Typesense's infix index; a query asks for it with
`->option('infix', 'always')` (or `fallback`), one mode per searched field. Asking for infix on a
field created without the index is refused by Typesense (`Could not find ... in the infix index`):
recreate a collection created before its fields declared it.

## Example

An entity, then a plain-PHP script that shows what the driver sends - the bundle's mapping, the
schema, the Typesense parameters of a query - and what happens with no server (the address is a
closed port):

```php
// Entity/Post.php
<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Omnisearch\Mapping\{Field, Index, Type};

#[ORM\Entity]
#[Index('post', options: ['typesense' => ['default_sorting_field' => 'publishedAt']])]
#[Field('title', type: Type::STRING, infix: true)]
#[Field('tags', type: Type::STRINGS, facet: true)]
class Post
{
    public function __construct(
        #[ORM\Id] #[ORM\Column] private int $id,
        #[ORM\Column] private string $title,
        #[ORM\Column(type: 'json')] private array $tags = [],
        #[ORM\Column] #[Field(type: Type::DATETIME, sort: true)] private \DateTimeImmutable $publishedAt = new \DateTimeImmutable('2026-01-01'),
    ) {
    }

    public function getId(): int { return $this->id; }
    public function getTitle(): string { return $this->title; }
    public function getTags(): array { return $this->tags; }
    public function getPublishedAt(): \DateTimeImmutable { return $this->publishedAt; }
}
```

```php
<?php // typesense.php

require __DIR__.'/vendor/autoload.php';
require __DIR__.'/Entity/Post.php';

use App\Entity\Post;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\{EntityManager, ORMSetup};
use Omnisearch\DBAL\Filter;
use Omnisearch\Exception\DriverException;
use Omnisearch\Mapping\AttributeLoader;
use Omnisearch\ORM\Query;
use Omnisearch\Registry;
use Omnisearch\Typesense\{Mapping, TypesenseDriver, TypesenseDriverFactory};

$metadata = AttributeLoader::load(Post::class);
echo json_encode(Mapping::toTypesense($metadata), JSON_UNESCAPED_SLASHES), "\n";

$query = (new Query('fuj'))->in('title')->where('tags', 'japan')->filter(new Filter('publishedAt', Filter::GREATER, 1767225600))
    ->facet('tags')->sortBy('publishedAt', 'desc')->perPage(5)->option('infix', 'always')->option('num_typos', 0);
echo json_encode(TypesenseDriver::query($metadata, $query->request())->getHeaders(), JSON_UNESCAPED_SLASHES), "\n";

$config = ORMSetup::createAttributeMetadataConfig([__DIR__.'/Entity'], true);
$config->enableNativeLazyObjects(true);                  // PHP 8.4
$entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config);

$registry = new Registry([new TypesenseDriverFactory(null, $entityManager)], ['default' => ['driver' => 'typesense', 'options' => [
    'url' => 'http://127.0.0.1:9', 'secret' => 'xyz', 'options' => ['connection_timeout_seconds' => 1, 'num_retries' => 0],
]]]);
$connection = $registry->connection();
echo json_encode($connection->schema($metadata), JSON_UNESCAPED_SLASHES), "\n";   // calls nothing
foreach (['search' => fn () => $connection->search($metadata, $query->request()), 'index' => fn () => $connection->index($metadata, new Post(1, 'Mount Fuji', ['japan']))] as $call => $run) {
    try {
        $response = $run();
        echo $call, ': ', $response ? $response->status.' '.$response->error : 'no answer', "\n";
    } catch (\Throwable $e) {
        echo $call, ': ', $e::class, ' ', $e->getCode(), "\n";
    }
}
try {
    (new TypesenseDriverFactory())->create(['url' => 'http://localhost:8108']);
} catch (Omnisearch\Exception\InvalidConfigException $e) {
    echo $e->getMessage(), "\n";
}
```

```
$ php typesense.php
{"connection":"default","class":"App\\Entity\\Post","fields":{"title":{"type":"string","infix":true,"options":[]},"tags":{"type":"string[]","facet":true,"options":[]},"publishedAt":{"type":"datetime","property":"publishedAt","options":{"sort":true}}},"default_sorting_field":"publishedAt","token_separators":["+","-","@","."," "],"symbols_to_index":["+"]}
{"query_by":"title","q":"fuj","infix":"always","per_page":5,"page":1,"sort_by":"publishedAt:desc","facet_by":"tags","filter_by":"tags:=`japan` && publishedAt:>1767225600","num_typos":0}
{"name":"post","fields":[{"name":"title","type":"string","property":null,"identifier":false,"discriminator":false,"facet":false,"infix":true},{"name":"tags","type":"string[]","property":null,"identifier":false,"discriminator":false,"facet":true,"infix":false},{"name":"publishedAt","type":"int64","property":"publishedAt","identifier":false,"discriminator":false,"facet":false,"infix":false,"sort":true},{"name":"id","type":"string","property":"id","identifier":true,"discriminator":false,"facet":false,"infix":false},{"name":"primary","type":"int32","property":"id","identifier":true,"discriminator":false,"facet":false,"infix":false}],"default_sorting_field":"publishedAt","token_separators":["+","-","@","."," "],"symbols_to_index":["+"]}
search: Symfony\Component\HttpClient\Psr18NetworkException 0
index: Symfony\Component\HttpClient\Psr18NetworkException 0
A Typesense connection of its own needs the option "secret" (and "url", or "host").
```

(run on 2026-10-26, PHP 8.4.27, no network: this checkout, typesense-bundle 2.x, typesense-php
5.2.0 and symfony/http-client 8.1 from an application's `vendor/`)

Against a server, `$connection->create($metadata)`, `index()` and
`$manager->finder(Post::class)->search($query)` do the rest; omnisearch's harness runs that whole
path in `bin/bare` ([harness](https://github.com/glitchr-omnisense/omnisearch/blob/1.x/docs/harness.md)).

## Errors

- A write Typesense refuses, or that cannot reach it: `Omnisearch\Exception\DriverException`, its
  message `Typesense: <the client's message>`, its code Typesense's HTTP status (503 when there was
  none), the bundle's or the client's exception as previous.
- A search: no exception by contract; a failed `Response` with the status and a message.
- A missing `secret`, or no object manager for a connection of its own: `InvalidConfigException`.
- An index the bundle does not know and no transformer to make it with: `\LogicException`.

> [!WARNING]
> Known defect: with symfony/http-client installed, php-http discovery gives the Typesense client Symfony's `Psr18Client`, whose `Psr18NetworkException` neither the bundle nor the driver catches: an unreachable server makes `search()` and `index()` throw it (code 0, seen in the example above), where a search should answer a failed `Response` and a write throw a `DriverException`. In omnisearch's harness, which installs no symfony/http-client, the contract holds.

## Verified, and not

- Verified (in omnisearch's harness, Typesense 30.0): the mapping equals the bundle's processed
  configuration and gives the same schema (`MappingTest`); requests give the bundle's own headers
  (`QueryTest`); recorded answers become Responses (`ResponseTest`: matched tokens of a string[]
  field, a padded facet_by, a refusal); a Symfony application with framework-bundle,
  doctrine-bundle, glitchr/typesense-bundle and omnisearch's bundle: the index hosted by the
  bundle, created by `typesense:create`, indexed by its listener, searched and loaded back by
  omnisearch (`HostedTest`); plain PHP with no class of a framework (omnisearch's `BareTest`).
- omnisearch's conformance suite (`Tests/TypesenseContractTest.php`): green on 2026-10-25 beside a
  Typesense 30.0 container; without a server its offline part runs (factory, options, mapping, an
  engine that does not answer) and the rest is skipped.
- On an application: lesringostars' four collections declared by attributes, their schemas those
  its server held, byte for byte (omnisearch's README).
- Run for this page: the example above, offline.
- Not verified: Typesense clusters of several nodes (the factory makes one node); Typesense Cloud;
  multi-search (not exposed by omnisearch).

## Responsibility

An unofficial client, affiliated with Typesense in no way (README). The package has no
Responsibility section of its own and links no terms of Typesense's.
