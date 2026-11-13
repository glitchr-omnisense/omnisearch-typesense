# Changelog

## [Unreleased]

## [1.0.0] - 2026-11-13

The first stable version. It requires `glitchr/typesense-bundle` `^2.0`, `omnisense/omnisearch` `^1.0` with no stability flag, so `composer require omnisearch/typesense` installs it in a project with Composer's default minimum-stability; the 1.x series follows semantic versioning. A tag `vX.Y.Z` is released by `.github/workflows/release.yml` once the package's CI passes on it.

### Added
- Its usage policy, on omnisense/contracts: `policy.json` (named in `composer.json`,
  `extra.omnisense.policy`): no limit - a self-hosted server has none, Typesense Cloud's terms
  publish none -, the documents the site's own, the server's API key required, the server's
  licence (GPL-3.0); verified 2026-11-10. The README's Usage policy section says which writes a
  replay could repeat (none doubled). `Tests/PolicyTest.php`.

### Changed
- Requires `omnisense/omnisearch`, the core's new name (Omniscient is now Omnisense); the repository is `glitchr-omnisense/omnisearch-typesense`.

### Changed (behaviour)
- A write that fails - `create()`, `drop()`, `index()`, `remove()` - throws omnisearch's
  `Omnisearch\Exception\DriverException` (Typesense's HTTP status as its code, 503 when there is
  none; the bundle's exception as `previous`) in place of glitchr/typesense-bundle's
  `TypesenseException`. Code that caught `TypesenseException` around these calls catches
  `DriverException` (or `OmnisearchException`) now. (8d650d8)

### Fixed
- An index the bundle does not know, with no transformer to make it, is omnisearch's
  `InvalidConfigException`, where it was a plain `\LogicException` (outside the family's
  `OmnisearchException`).
- A Typesense server that cannot be reached no longer lets the HTTP client's exception escape:
  with symfony/http-client installed, php-http's discovery gives typesense-php Symfony's
  `Psr18Client`, whose `Psr18NetworkException` neither the bundle nor the driver caught, so
  `search()` and every write threw it (code 0). The driver catches the PSR-18 interfaces
  (`Psr\Http\Client\ClientExceptionInterface`): a search answers a failed Response (503,
  `Typesense not reached: ...`), `index()`, `remove()`, `create()` and `drop()` throw a
  `DriverException` (503). The package requires `psr/http-client` (already there through
  typesense-php), and its tests symfony/http-client.
- A refused or unreachable search answers a Response whose message says what the status means,
  not an empty message. (8d650d8)
- `remove()` no longer takes an unreachable server for a document that is not there. (8d650d8)

### Added
- The README's Responsibility section, as every package of the family has.
- The package runs omnisearch's conformance suite (`Tests/TypesenseContractTest.php`,
  `Omnisearch\Testing\DriverContractTests`). (8d650d8)
