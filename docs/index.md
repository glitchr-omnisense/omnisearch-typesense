---
title: omnisearch/typesense
order: 1
---

# omnisearch/typesense

The driver of omnisearch for Typesense. See the [README](../README.md) for its parts.

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

The classes declaring an `#[Index]` become the bundle's collections; `bin/console typesense:create`
then `typesense:action upsert` create and fill them, the bundle's Doctrine listener keeps them, and
`Omnisearch\ORM\Finder` searches them. A collection still in the bundle's YAML lives beside them;
declare a collection in one place, not both.

## On its own

```php
new TypesenseDriverFactory(null, $entityManager)    // options: url (or scheme, host, port), secret, prefix, options (the client's)
```

The collections are made from the metadata on first use (`$manager->create()` creates them on the
server).

## Infix

A field declared `infix: true` is created with Typesense's infix index; a query asks for it with
`->option('infix', 'always')` (or `fallback`), one mode per searched field. Asking for infix on a
field created without the index is refused by Typesense (`Could not find ... in the infix index`):
recreate a collection created before its fields declared it.
