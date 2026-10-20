# omnisearch/typesense

[Typesense](https://typesense.org) for [omniscient/omnisearch](https://github.com/glitchr-studio/omnisearch),
over [glitchr/typesense-bundle](https://github.com/glitchr-studio/typesense-bundle)'s
framework-free part: the indexes declared by omnisearch's attributes become the bundle's
collections, objects its documents, omnisearch's requests its queries, its responses omnisearch's.
Nothing the bundle does is written again here.

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
- Not verified: Typesense clusters of several nodes; `multiSearch` (not exposed by omnisearch).

MIT. An unofficial client, affiliated with Typesense in no way.
