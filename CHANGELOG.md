# Changelog

All notable changes to `rdw-opendata-php` will be documented in this file.

## v0.5.0 - 2026-06-25

### Added

- `NiekNijland\RDW\Testing\FakeRdw` — a test double for `Rdw`, backed by an in-memory `FakeSocrataClient`, that runs the real query builder and hydrator against seeded rows with no HTTP. Seed rows per dataset (`withRegisteredVehicles()`, `withRegisteredVehicleFuels()`, `withRows()`), simulate transient failures (`failWith()` / `clearFailure()`), and assert on `recordedRequests()`.

## v0.4.0 - 2026-05-30

Drop 6 fuel columns (dataset 8ys7-d773) that RDW removed from the live dataset — querying them returned HTTP 400 `no-such-column`, so the generated enum cases were dead.

Removed:

- `brandstofverbruik_buiten` (FuelConsumptionOuter)
- `brandstofverbruik_stad` (FuelConsumptionCity)
- `roetuitstoot` (SootEmissions)
- `max_vermogen_60_minuten` (MaximumPower60Minutes)
- `actie_radius_enkel_elektrisch_stad_wltp` (WltpRangeElectricOnlyCity)
- `actie_radius_extern_opladen_stad_wltp` (WltpRangeExternalChargingCity)

**Breaking:** removes the corresponding `RegisteredVehicleFuelField` enum cases and `RegisteredVehicleFuel` properties.

## v0.3.1 - 2026-05-17

### Fixed

- `QueryBuilder::groupByRaw()` is now idempotent for the same expression, mirroring the typed `groupBy()` counterpart. Two calls with a string-equal expression no longer emit a duplicated `$group` column that RDW would reject with HTTP 400.

## v0.3.0 - 2026-05-17

### Added

- `QueryBuilder::groupByRaw(string $expression)` — escape hatch for grouping by an arbitrary SoQL expression (e.g. `date_trunc_ym(datum_eerste_toelating_dt)`). Mirrors the existing `selectRaw` / `orderByRaw` pattern; the expression is appended verbatim and must reference RDW field keys, not English aliases. Enables consumers (notably LLM-driven query plans that want to bucket dates with `date_trunc_y` / `date_trunc_ym` / `date_trunc_ymd`) to group by a derived expression without dropping back to `Rdw::rawRows()`.

## v0.2.0 - 2026-05-16

### Added

- `Schema\ValueVocabulary` — optional value-list metadata that can be attached to a `FieldDescriptor` via the new `vocabulary` constructor argument. Carries a `values` list plus an `exhaustive` flag and is intended for schema-introspection consumers (notably LLM-driven query builders) that want to render an enum-shaped JSON schema or anchor prompt examples without round-tripping the live API.
- `ValueVocabulary::closed(...)` and `ValueVocabulary::examples(...)` factory helpers — the first communicates "this is the full known list" (small Dutch code lists like vehicle type and color), the second communicates "these are common values; the field is open" (brand, commercial name).
- `DatasetSchema::fieldsWithVocabulary()` — returns only the exposed descriptors that carry vocabulary metadata, so consumers don't have to filter manually.
- Vocabularies wired up on `RegisteredVehicles` for `VehicleType` (closed, 5 values), `PrimaryColor` / `SecondaryColor` (closed, 14 values, identical list), `Brand` and `CommercialName` (examples, 21 / 19 popular values).

### Notes

- Hydration behaviour is unchanged. Vocabularies are descriptive metadata only; RDW occasionally introduces new values and the library deliberately doesn't reject them.

## v0.1.1 - 2026-05-16

### Fixed

- `select()` and `groupBy()` are now idempotent for the same field. Repeating the same column previously emitted a malformed `$select` / `$group` that RDW rejects with HTTP 400; the builder now silently dedupes so callers that pass the same field through two code paths (e.g. a select + groupBy orchestration) generate a valid request.

## v0.1.0 — initial release - 2026-05-16

Initial release of the typed PHP client for the RDW Open Data `Voertuigen` datasets.

### Added

- Typed entry point `Rdw` exposing 10 RDW `Voertuigen` datasets (registered vehicles, fuels, axles, bodyworks, bodywork specifications, classes, subcategories, special features, track sets, odometer judgement explanations).
- Generated field enums under `src/Fields/` and typed record value objects under `src/Records/`, with `CarbonImmutable` UTC dates and `Ja`/`Nee` → `bool` casts.
- Immutable fluent SoQL query builder covering every documented Socrata parameter (`$select`, `$where`, `$order`, `$group`, `$having`, `$limit`, `$offset`, `$q`) with ~30 chainable methods.
- Typed `Relations` loader for navigating between datasets (`fuelsFor`, `axlesFor`, `bodyworksFor`, `subcategoriesFor`, `specialFeaturesFor`, `trackSetsFor`, `odometerJudgementFor`, `specificationsFor`, `vehicleClassesFor`).
- Schema generator (`bin/rdw-generate`, `composer rdw:generate`) with CI drift detection via `composer rdw:generate:check`.
- Typed exception hierarchy rooted at `RdwException` with dedicated `HttpException`, `RateLimitException`, `DatasetNotFoundException`, and `MissingFieldOverrideException`.
- Raw escape hatches: `Rdw::rawRows()` / `Rdw::rawMetadata()`, plus `whereRaw` / `selectRaw` / `orderByRaw` / `havingRaw` on the builder.

### Requirements

- PHP 8.4+

### Install

```bash
composer require nieknijland/rdw-opendata-php


```
## v0.1.0 - 2026-05-16

Initial release.

### Added

- Typed entry point `Rdw` exposing 10 RDW `Voertuigen` datasets (registered vehicles, fuels, axles, bodyworks, bodywork specifications, classes, subcategories, special features, track sets, odometer judgement explanations).
- Generated field enums under `src/Fields/` and typed record value objects under `src/Records/`, with `CarbonImmutable` UTC dates and `Ja`/`Nee` → `bool` casts.
- Immutable fluent SoQL query builder covering every documented Socrata parameter (`$select`, `$where`, `$order`, `$group`, `$having`, `$limit`, `$offset`, `$q`) with ~30 chainable methods including `where`, `whereIn`, `whereNotIn`, `whereNull`, `whereNotNull`, `whereBetween`, `whereNotBetween`, `whereLike`, `whereStartsWith`, `whereContains`, `whereAny`, `whereNot`, `search`, `select`, `selectRaw`, `groupBy`, `orderBy`, `orderByRaw`, `limit`, `offset`, `distinct`, `count`, `countDistinct`, `sum`, `avg`, `min`, `max`, `havingRaw`, and the terminators `get`, `first`, `exists`, `pluck`, `iterate`, `getProjection`, `toSoqlParams`.
- Typed `Relations` loader for navigating between datasets (e.g. `fuelsFor`, `axlesFor`, `bodyworksFor`, `subcategoriesFor`, `specialFeaturesFor`, `trackSetsFor`, `odometerJudgementFor`, `specificationsFor`, `vehicleClassesFor`).
- Schema generator (`bin/rdw-generate`, `composer rdw:generate`) producing `src/Fields/*.php` and `src/Records/*.php` from override classes plus checked-in `metadata/*.json` snapshots, with CI drift detection via `composer rdw:generate:check`.
- Typed exception hierarchy rooted at `RdwException`, with dedicated `HttpException` (carries `statusCode`, `responseBody`), `RateLimitException` (carries `retryAfterSeconds` from the `Retry-After` header on HTTP 429), `DatasetNotFoundException`, and `MissingFieldOverrideException`.
- Raw escape hatches: `Rdw::rawRows()` and `Rdw::rawMetadata()` for ad-hoc queries, plus `whereRaw` / `selectRaw` / `orderByRaw` / `havingRaw` on the builder.
