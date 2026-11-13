# omnisearch/typesense

[Typesense](https://typesense.org) for [omnisense/omnisearch](https://github.com/glitchr-omnisense/omnisearch),
over [glitchr/typesense-bundle](https://github.com/glitchr-studio/typesense-bundle)'s
framework-free part: the indexes declared by omnisearch's attributes become the bundle's
collections, objects its documents, omnisearch's requests its queries, its responses omnisearch's.
Nothing the bundle does is written again here.

## Install and a first call

```sh
composer require omnisearch/typesense
```

No server for this first call: an index declared by omnisearch's attributes, then what the driver
would send for it - the collection's fields as the bundle maps them, and a query as Typesense's
search parameters. A real call needs a Typesense server and its API key: one's own (the Docker image
`typesense/typesense`, started with `--api-key`; no account) or a Typesense Cloud cluster (an
account). The objects indexed are Doctrine entities: the driver of its own needs Doctrine's object
manager ([docs/index.md](docs/index.md#example)).

```php
<?php

require __DIR__.'/vendor/autoload.php';

use Omnisearch\Mapping\{AttributeLoader, Field, Index, Type};
use Omnisearch\ORM\Query;
use Omnisearch\Typesense\{Mapping, TypesenseDriver};

#[Index('post')]
#[Field('title', type: Type::STRING, infix: true)]
#[Field('tags', type: Type::STRINGS, facet: true)]
final class Post
{
    public function __construct(public int $id = 0, public string $title = '', public array $tags = [])
    {
    }
}

$metadata = AttributeLoader::load(Post::class);
echo json_encode(array_keys(Mapping::toTypesense($metadata)['fields'])), "\n";   // ["title","tags"]
$query = (new Query('fuj'))->in('title')->where('tags', 'japan')->facet('tags')->perPage(5);
echo json_encode(TypesenseDriver::query($metadata, $query->request())->getHeaders(), JSON_UNESCAPED_SLASHES), "\n";   // {"query_by":"title","q":"fuj",...,"filter_by":"tags:=`japan`"}
```

Against a server, with Doctrine's entity manager:

```php
use Omnisearch\Typesense\TypesenseDriverFactory;

$registry = new Registry([new TypesenseDriverFactory(null, $entityManager)], ['default' => ['driver' => 'typesense', 'options' => [
    'url' => 'http://localhost:8108', 'secret' => 'xyz', 'prefix' => 'dev_',
]]]);
```

| | |
|---|---|
| `Mapping` | an index's metadata as the bundle's mapping: the very array its `typesense.mappings` configuration gives, so the same collection, schema and documents. `Type::INT` is `int32`, `BIGINT` `int64`, `DATETIME` the bundle's `datetime` (a timestamp); a field's `sort`, `optional`, `locale` and `options['typesense']` are the bundle's field options; the index's `options['typesense']` its `default_sorting_field`, `token_separators`, `symbols_to_index` |
| `TypesenseDriver` | the bundle's `TypesenseManager`, `TypesenseCollection` (an index and its discriminator sub-collections), `EntityTransformer`, `Query` and `Response`: a `Request` becomes the headers an application wrote with the bundle's setters (`infix` and Typesense's other parameters by `option()`), a `Filter` Typesense's `filter_by` syntax; `schemas()` gives every collection's |
| `TypesenseDriverFactory` | over the bundle's manager (Symfony, autowired; the `connection` option names the bundle's connection), or on its own from `url`/`secret`/`prefix` with Doctrine's object manager |
| `Bridge\Symfony\TypesenseHost` | with the bundle in a Symfony application, the indexes of a `typesense` connection are prepended to the bundle's configuration: its commands and Doctrine listener serve them |

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
  engine that does not answer) and the rest is skipped. Found and fixed on the way: a refused or
  unreachable search answered an empty message (the bundle's finder keeps Typesense's outside
  debug) - it says what the status means; a write that failed threw the bundle's
  `TypesenseException` - it is omnisearch's `DriverException`, the status as its code, and
  `remove()` no longer takes an unreachable server for a document not there.
- Not verified: Typesense clusters of several nodes; `multiSearch` (not exposed by omnisearch).

## Responsibility

This package is an independent, unofficial driver: it is not affiliated with, endorsed by or
sponsored by Typesense, Inc. It talks to the Typesense server its user runs or rents, through
glitchr/typesense-bundle and typesense/typesense-php, and sends that server nothing but the
documents and queries the application gives it. Its MIT licence covers this package's code only.

Whoever installs and runs it is responsible for complying with the terms of the Typesense server
they use (Typesense's own licence for a server they run, Typesense Cloud's terms for a rented one),
and for what they index and search. The authors publish it free of charge, make no
commercial use of it, and accept no liability for how it is used (see [LICENSE](LICENSE)).

## Usage policy

`policy.json` (omnisense/contracts' schema, named in `composer.json` as `extra.omnisense.policy`)
says it as data: no limit (`[]`) - a server the site runs has none but its machine's, and Typesense
Cloud's terms publish none (usage is billed, the cluster's size bounds it); the documents are the
site's own; the key is the server's API key. Read on 2026-11-10. A site adds a limit of its own
through omnisense/contracts' `tighten()`; nothing in this driver counts calls.

Writes are never doubled by a replay: a document is upserted by its identifier and removed by its
identifier (both idempotent); typesense-php retries a request after a 5xx or a network failure,
and the only writes that are not idempotent - a collection's creation or drop - answer 409 or 404
on a replay rather than being done twice. omnisearch adds no retry of its own.

License: MIT.
