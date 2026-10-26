# Changelog

## Unreleased (1.x)

### Changed
- Requires `omnisense/omnisearch`, the core's new name (Omniscient is now Omnisense); the repository is `glitchr-omnisense/omnisearch-typesense`.

### Changed (behaviour)
- A write that fails - `create()`, `drop()`, `index()`, `remove()` - throws omnisearch's
  `Omnisearch\Exception\DriverException` (Typesense's HTTP status as its code, 503 when there is
  none; the bundle's exception as `previous`) in place of glitchr/typesense-bundle's
  `TypesenseException`. Code that caught `TypesenseException` around these calls catches
  `DriverException` (or `OmnisearchException`) now. (8d650d8)

### Fixed
- A refused or unreachable search answers a Response whose message says what the status means,
  not an empty message. (8d650d8)
- `remove()` no longer takes an unreachable server for a document that is not there. (8d650d8)

### Added
- The package runs omnisearch's conformance suite (`Tests/TypesenseContractTest.php`,
  `Omnisearch\Testing\DriverContractTests`). (8d650d8)
