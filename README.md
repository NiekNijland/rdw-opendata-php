# rdw-opendata-php

[![Latest Version on Packagist](https://img.shields.io/packagist/v/nieknijland/rdw-opendata-php.svg?style=flat-square)](https://packagist.org/packages/nieknijland/rdw-opendata-php)
[![Tests](https://img.shields.io/github/actions/workflow/status/NiekNijland/rdw-opendata-php/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/NiekNijland/rdw-opendata-php/actions/workflows/run-tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/nieknijland/rdw-opendata-php.svg?style=flat-square)](https://packagist.org/packages/nieknijland/rdw-opendata-php)

A typed PHP client for the [RDW Open Data](https://opendata.rdw.nl) `Voertuigen`
datasets. Avoids magic Dutch field strings: every dataset has a generated
field enum with English case names, every record is a typed value object,
and dates are `CarbonImmutable` in UTC.

## Installation

```bash
composer require nieknijland/rdw-opendata-php
```

Requires PHP 8.4+.

## Quick start

```php
use NiekNijland\RDW\Rdw;
use NiekNijland\RDW\Fields\RegisteredVehicleField;
use NiekNijland\RDW\Query\SortDirection;

$rdw = new Rdw();

$vehicles = $rdw->registeredVehicles()
    ->where(RegisteredVehicleField::CommercialName, 'POLO')
    ->where(RegisteredVehicleField::CanBeTransferred, true)
    ->orderBy(RegisteredVehicleField::RegistrationDate, SortDirection::Desc)
    ->limit(10)
    ->get();

foreach ($vehicles as $vehicle) {
    echo $vehicle->licensePlate.' '.$vehicle->brand.' '.$vehicle->commercialName.PHP_EOL;
    echo '  registered: '.$vehicle->registrationDate?->toDateString().PHP_EOL;
    echo '  apk: '.$vehicle->apkExpiryDate?->toDateString().PHP_EOL;
}
```

## Configuration

```php
use NiekNijland\RDW\Rdw;
use NiekNijland\RDW\Http\Configuration;

$rdw = new Rdw(new Configuration(
    appToken: 'YOUR_SOCRATA_APP_TOKEN', // optional, raises your rate limit
    userAgent: 'your-app/1.0',
    timeoutSeconds: 10.0,
));
```

The HTTP layer raises `NiekNijland\RDW\Exceptions\RateLimitException` on HTTP
`429` (with `retryAfterSeconds` extracted from the `Retry-After` header) and
`HttpException` on any other non-2xx response.

## Supported datasets

| Method on `Rdw`                                | RDW dataset id   | Record class                                                       |
|------------------------------------------------|------------------|--------------------------------------------------------------------|
| `registeredVehicles()`                         | `m9d7-ebf2`      | `Records\RegisteredVehicle`                                        |
| `registeredVehicleFuels()`                     | `8ys7-d773`      | `Records\RegisteredVehicleFuel`                                    |
| `registeredVehicleAxles()`                     | `3huj-srit`      | `Records\RegisteredVehicleAxle`                                    |
| `registeredVehicleBodyworks()`                 | `vezc-m2t6`      | `Records\RegisteredVehicleBodywork`                                |
| `registeredVehicleBodyworkSpecifications()`    | `jhie-znh9`      | `Records\RegisteredVehicleBodyworkSpecification`                   |
| `registeredVehicleClasses()`                   | `kmfi-hrps`      | `Records\RegisteredVehicleClass`                                   |
| `registeredVehicleSubcategories()`             | `2ba7-embk`      | `Records\RegisteredVehicleSubcategory`                             |
| `registeredVehicleSpecialFeatures()`           | `7ug8-2dtt`      | `Records\RegisteredVehicleSpecialFeature`                          |
| `registeredVehicleTrackSets()`                 | `3xwf-ince`      | `Records\RegisteredVehicleTrackSet`                                |
| `odometerJudgementExplanations()`              | `jqs4-4kvw`      | `Records\OdometerJudgementExplanation`                             |

## Query builder

```php
$builder = $rdw->registeredVehicles()
    ->where(RegisteredVehicleField::Brand, 'VOLKSWAGEN')
    ->whereIn(RegisteredVehicleField::VehicleType, ['Personenauto', 'Bedrijfsauto'])
    ->whereBetween(RegisteredVehicleField::FirstAdmissionDate, $from, $to)
    ->select(
        RegisteredVehicleField::LicensePlate,
        RegisteredVehicleField::Brand,
        RegisteredVehicleField::CommercialName,
    )
    ->orderBy(RegisteredVehicleField::RegistrationDate, SortDirection::Desc)
    ->limit(25);

$vehicles = $builder->get();        // list<RegisteredVehicle>
$first    = $builder->first();      // ?RegisteredVehicle
$any      = $builder->exists();     // bool — single-row probe, no hydration
$plates   = $builder->pluck(RegisteredVehicleField::LicensePlate); // list<string>
```

The builder is immutable: every chained method returns a clone. You can
share a partially built query across functions safely.

### Where predicates

| Method | Emits | Notes |
|---|---|---|
| `where($field, $value, $op = '=')` | `field op value` | Operator must be one of `=`, `!=`, `<>`, `<`, `<=`, `>`, `>=`, `LIKE`, `NOT LIKE`. Rejects `null` — use `whereNull` instead. |
| `whereIn($field, $values)` | `field IN (…)` | Rejects an empty list. |
| `whereNotIn($field, $values)` | `field NOT IN (…)` | |
| `whereNull($field)` / `whereNotNull($field)` | `field IS [NOT] NULL` | |
| `whereBetween($field, $min, $max)` | `field BETWEEN x AND y` | Encodes dates as Socrata datetime literals. |
| `whereNotBetween($field, $min, $max)` | `field NOT BETWEEN x AND y` | |
| `whereLike($field, $pattern)` | `field LIKE '…'` | SQL `%` wildcards, case-sensitive. |
| `whereStartsWith($field, $prefix)` | `starts_with(field, '…')` | Case-sensitive. |
| `whereContains($field, $sub)` | `contains(field, '…')` | **Case-insensitive** per Socrata. |
| `whereAny(fn ($q) => …)` | `((a) OR (b) OR …)` | OR-group. The callback **must return** the chained builder — the builder is immutable, a void closure throws. |
| `whereNot(fn ($q) => …)` | `NOT ((a) AND (b))` | Same callback contract as `whereAny`. Combine with `whereAny` inside for `NOT (a OR b)`. |
| `whereRaw($expression)` | passes through | Use RDW field keys (not English aliases). |
| `search($query)` | sets `$q` | Socrata full-text search across every string column. Whitespace-tokenized; rows must contain every token. |

Every field-typed argument takes a case of the dataset's generated field
enum — `RegisteredVehicleField`, `RegisteredVehicleFuelField`, etc. The
generated enums live under `src/Fields/`; PascalCase case names map to the
RDW field key, e.g. `RegisteredVehicleField::CommercialName` →
`handelsbenaming`.

### Selection, ordering, pagination

| Method | Emits | Notes |
|---|---|---|
| `select($field, …)` | `$select=fields` | Variadic; chained calls accumulate. Pass field-enum cases. |
| `selectRaw($expr, $alias = null)` | appends to `$select` | Use for arbitrary SoQL projections. Aliases must match `[A-Za-z_][A-Za-z0-9_]*`. |
| `groupBy($field, …)` | `$group=fields` | Combine with the aggregate helpers below. |
| `orderBy($field, $direction = Asc)` | `$order=field DIR` | `SortDirection::Asc` / `SortDirection::Desc`. |
| `orderByRaw($expr)` | appends to `$order` | Use for arbitrary SoQL like `count DESC`. |
| `limit($n)` | `$limit=$n` | Must be ≥ 1. |
| `offset($n)` | `$offset=$n` | Must be ≥ 0. |

### Aggregates and projections

Combine these with `groupBy()` + `getProjection()` for analytic queries:

```php
$top = $rdw->registeredVehicles()
    ->select(RegisteredVehicleField::Brand)
    ->count(null, 'n')
    ->min(RegisteredVehicleField::SeatCount, 'min_seats')
    ->max(RegisteredVehicleField::SeatCount, 'max_seats')
    ->groupBy(RegisteredVehicleField::Brand)
    ->havingRaw('count(*) > 100000')
    ->orderByRaw('n DESC')
    ->limit(5)
    ->getProjection();
```

| Method | Emits |
|---|---|
| `count($field = null, $alias = 'count')` | `count(field)` or `count(*)` |
| `countDistinct($field, $alias = 'count')` | `count(distinct field)` |
| `sum($field, $alias = 'sum')` | `sum(field)` |
| `avg($field, $alias = 'avg')` | `avg(field)` |
| `min($field, $alias = 'min')` | `min(field)` |
| `max($field, $alias = 'max')` | `max(field)` |
| `distinct()` | prepends `distinct ` to `$select` (requires at least one `select()` call). |
| `havingRaw($expr)` | sets `$having`; reference the aliases you used above. |
| `selectRaw($expr, $alias = null)` | escape hatch for arbitrary SoQL projections. |

### Executing the query

| Method | Returns | Notes |
|---|---|---|
| `get()` | `list<TRecord>` | Single page, hydrated. |
| `first()` | `?TRecord` | Adds `$limit=1`. |
| `exists()` | `bool` | Single-row probe; skips hydration. |
| `pluck($field)` | `list<scalar\|CarbonImmutable\|null>` | One column's values, cast through the same `ValueCaster` records use. |
| `iterate($pageSize = 1000)` | `Generator<int, TRecord>` | Pages lazily. Outer `limit()` is a hard ceiling. See [Pagination](#pagination). |
| `getProjection()` | `list<array<string, mixed>>` | Raw associative rows. Use for aggregate / groupBy / selectRaw queries that don't fit the record schema. |
| `toSoqlParams()` | `array<string, string>` | The `$select`/`$where`/`$order`/… map as it will be sent. Useful for debugging — see the [Cookbook](#cookbook). |

### Boolean fields

Fields backed by RDW's `Ja`/`Nee` text values are typed as `bool` in the
records and in the where-clauses:

```php
$rdw->registeredVehicles()
    ->where(RegisteredVehicleField::CanBeTransferred, true)  // → tenaamstellen_mogelijk='Ja'
    ->where(RegisteredVehicleField::HasOpenRecall, false)    // → openstaande_terugroepactie_indicator='Nee'
    ->get();
```

### Dates

Calendar-date fields (`*_dt`) hydrate to `CarbonImmutable` at midnight UTC.
When you pass any `DateTimeInterface` to `where()` for a date field, it is
serialized as a Socrata datetime literal:

```php
use Carbon\CarbonImmutable;

$rdw->registeredVehicles()
    ->where(RegisteredVehicleField::FirstAdmissionDate, CarbonImmutable::parse('1991-01-01'), '>=')
    ->where(RegisteredVehicleField::FirstAdmissionDate, CarbonImmutable::parse('1992-01-01'), '<')
    ->get();
```

### Raw SoQL escape hatch

For Socrata expressions the typed API does not model — `date_extract`,
`lower`, `upper`, geospatial predicates, CASE expressions, etc. — fall
back to `whereRaw` / `selectRaw` / `orderByRaw` / `havingRaw`. Raw fragments
take RDW field keys, not English aliases:

```php
$rdw->registeredVehicles()
    ->where(RegisteredVehicleField::Brand, 'VOLKSWAGEN')
    ->whereRaw("date_extract_y(datum_eerste_toelating_dt) = 2020")
    ->getProjection();
```

To bypass the typed builder entirely — for ad-hoc queries against a dataset
that doesn't yet have a typed wrapper, or to inspect the metadata document
RDW publishes — use the two passthrough methods on `Rdw`:

```php
// Raw row fetch. The $query map is passed through to Socrata as-is.
$rows = $rdw->rawRows(DatasetId::RegisteredVehicles, [
    '$where'  => "kenteken = 'AB-12-CD'",
    '$select' => 'kenteken, merk, handelsbenaming',
]);

// Raw metadata document (column types, descriptions, last update, …).
$meta = $rdw->rawMetadata(DatasetId::RegisteredVehicles);
```

### Pagination

`get()` issues a single request and returns one page. For large result sets
use `iterate()`, which yields hydrated records lazily and pages internally:

```php
foreach ($rdw->registeredVehicles()
    ->where(RegisteredVehicleField::Brand, 'VOLKSWAGEN')
    ->iterate(pageSize: 1000) as $vehicle) {
    // process one vehicle at a time without buffering the whole set
}
```

An outer `->limit(N)` is respected as a hard ceiling.

## Relations

A registered vehicle is the entry point for every other dataset. Relations
return pre-filtered query builders, so you can chain more filters before
hitting the API:

```php
$vehicle = $rdw->registeredVehicles()
    ->where(RegisteredVehicleField::LicensePlate, '6ZNS30')
    ->first();

$fuels         = $rdw->relations()->fuelsFor($vehicle)->get();
$axles         = $rdw->relations()->axlesFor($vehicle)->get();
$bodyworks     = $rdw->relations()->bodyworksFor($vehicle)->get();
$subcategories = $rdw->relations()->subcategoriesFor($vehicle)->get();
$specials      = $rdw->relations()->specialFeaturesFor($vehicle)->get();
$trackSets     = $rdw->relations()->trackSetsFor($vehicle)->get();
$judgement     = $rdw->relations()->odometerJudgementFor($vehicle)->first();

// Composite key: bodywork → bodywork specifications / vehicle classes
$bodywork        = $bodyworks[0];
$specifications  = $rdw->relations()->specificationsFor($bodywork)->get();
$vehicleClasses  = $rdw->relations()->vehicleClassesFor($bodywork)->get();
```

Relations throw `RdwException` when a required join key is `null` on the
source record — that prevents accidentally querying "everything where
kenteken IS NULL".

## Cookbook

Worked examples against the live RDW API. Numbers come from a run on
`2026-05-16`.

**Count: how many white VW Ups are insured?**

```php
$count = $rdw->registeredVehicles()
    ->where(RegisteredVehicleField::Brand, 'VOLKSWAGEN')
    ->where(RegisteredVehicleField::CommercialName, 'UP')
    ->where(RegisteredVehicleField::PrimaryColor, 'WIT')
    ->where(RegisteredVehicleField::IsWamInsured, true)
    ->count()
    ->getProjection();
// → [['count' => '21084']]
```

**Group + having: the top-5 brands with more than 100k registrations**

```php
$top = $rdw->registeredVehicles()
    ->select(RegisteredVehicleField::Brand)
    ->count(null, 'n')
    ->min(RegisteredVehicleField::SeatCount, 'min_seats')
    ->max(RegisteredVehicleField::SeatCount, 'max_seats')
    ->groupBy(RegisteredVehicleField::Brand)
    ->havingRaw('count(*) > 100000')
    ->orderByRaw('n DESC')
    ->limit(5)
    ->getProjection();
// → [
//     ['merk' => 'VOLKSWAGEN', 'n' => '1521987', 'min_seats' => '1', 'max_seats' => '23'],
//     ['merk' => 'PEUGEOT',    'n' => '852665',  'min_seats' => '1', 'max_seats' => '17'],
//     ...
// ]
```

**Fuzzy model search with OR-group**

```php
$count = $rdw->registeredVehicles()
    ->where(RegisteredVehicleField::Brand, 'VOLKSWAGEN')
    ->whereAny(fn ($q) => $q
        ->whereStartsWith(RegisteredVehicleField::CommercialName, 'GTI')
        ->whereContains(RegisteredVehicleField::CommercialName, 'R32'))
    ->where(RegisteredVehicleField::IsWamInsured, true)
    ->count()
    ->getProjection();
```

`whereContains` is case-insensitive (Socrata `contains()`), `whereStartsWith`
is case-sensitive (Socrata `starts_with()`). Use `whereLike` with `%`
wildcards if you need SQL-style patterns.

**Date range: VWs first admitted in 2020-2024**

```php
use Carbon\CarbonImmutable;

$count = $rdw->registeredVehicles()
    ->where(RegisteredVehicleField::Brand, 'VOLKSWAGEN')
    ->whereBetween(
        RegisteredVehicleField::FirstAdmissionDate,
        CarbonImmutable::parse('2020-01-01', 'UTC'),
        CarbonImmutable::parse('2024-12-31', 'UTC'),
    )
    ->count()
    ->getProjection();
// → [['count' => '348046']]
```

**Pluck: every license plate of a model variant**

```php
$plates = $rdw->registeredVehicles()
    ->where(RegisteredVehicleField::Brand, 'VOLKSWAGEN')
    ->where(RegisteredVehicleField::CommercialName, 'UP')
    ->where(RegisteredVehicleField::PrimaryColor, 'WIT')
    ->orderBy(RegisteredVehicleField::LicensePlate)
    ->limit(5)
    ->pluck(RegisteredVehicleField::LicensePlate);
// → ['00TKZ5', '00TKZ6', '00TPB4', '00TPL4', '00TTL4']
```

`pluck` of a date field returns `CarbonImmutable` instances; the cast goes
through the same `ValueCaster` the typed records use.

**Existence probe (no hydration)**

```php
$found = $rdw->registeredVehicles()
    ->where(RegisteredVehicleField::LicensePlate, 'ZZ-ZZ-ZZ')
    ->exists();
// → false
```

**Full-text search across all string columns**

```php
$hit = $rdw->registeredVehicles()
    ->search('polo gti')
    ->where(RegisteredVehicleField::Brand, 'VOLKSWAGEN')
    ->select(
        RegisteredVehicleField::LicensePlate,
        RegisteredVehicleField::CommercialName,
    )
    ->first();
```

`$q` is tokenized on whitespace; rows must contain every token. It's slow
on big datasets without an app token — narrow it with a `where()` first.

**Lazy iteration over a large fleet**

```php
foreach ($rdw->registeredVehicles()
    ->where(RegisteredVehicleField::Brand, 'VOLKSWAGEN')
    ->iterate(pageSize: 1000) as $vehicle) {
    // one row at a time, no buffering
}
```

An outer `->limit(N)` caps the iteration.

**Debugging: inspect the SoQL the builder will emit**

```php
$params = $rdw->registeredVehicles()
    ->where(RegisteredVehicleField::Brand, 'VOLKSWAGEN')
    ->whereBetween(
        RegisteredVehicleField::FirstAdmissionDate,
        CarbonImmutable::parse('2020-01-01', 'UTC'),
        CarbonImmutable::parse('2024-12-31', 'UTC'),
    )
    ->toSoqlParams();
// → [
//   '$where' => "(merk = 'VOLKSWAGEN') AND "
//             . "(datum_eerste_toelating_dt BETWEEN '2020-01-01T00:00:00.000' "
//             . "AND '2024-12-31T00:00:00.000')",
// ]
```

`toSoqlParams()` returns the exact `$select`/`$where`/`$order`/etc. that
will be sent to Socrata, without performing the request.

## Exceptions

All exceptions extend `NiekNijland\RDW\Exceptions\RdwException`. Catch the
base class for blanket handling, or the specific type for control flow.

| Exception | Thrown when |
|---|---|
| `HttpException` | Non-2xx response (excluding 429) or transport failure. Carries `statusCode` and `responseBody`. |
| `RateLimitException` | HTTP 429. Carries `retryAfterSeconds` extracted from the `Retry-After` header. |
| `DatasetNotFoundException` | Asking the registry for an unknown dataset id. |
| `RdwException` | Catch-all base: invalid relation join keys, malformed JSON, scalar payloads from Socrata, etc. |
| `MissingFieldOverrideException` | Generator-time only: RDW exposes a field with no matching override. |

## Schema introspection (for AI / dynamic consumers)

`SchemaRegistry` is the entry point for anything that needs to reason about
the dataset shape at runtime — e.g. an LLM-driven natural-language → SoQL
pipeline that has to know which fields exist, how they're typed, and which
values they accept.

```php
use NiekNijland\RDW\Datasets\DatasetId;
use NiekNijland\RDW\Schema\SchemaRegistry;

$schema = (new SchemaRegistry())->get(DatasetId::RegisteredVehicles);

foreach ($schema->exposedFields() as $field) {
    // $field->enumCase     — 'CommercialName'
    // $field->rdwKey       — 'handelsbenaming'
    // $field->propertyName — 'commercialName'
    // $field->cast         — CastType case
    // $field->vocabulary   — ?ValueVocabulary
}

foreach ($schema->fieldsWithVocabulary() as $field) {
    $vocab = $field->vocabulary;
    // $vocab->values     — list<string>
    // $vocab->exhaustive — true ⇒ full known set, false ⇒ representative examples
}
```

`ValueVocabulary::closed(...)` marks small Dutch code lists (vehicle type,
colors) whose full set is known; `ValueVocabulary::examples(...)` marks
fields like brand and commercial name where the list is too large to
enumerate and consumers get a curated sample to anchor prompts or
autocompletion. The library never enforces the vocabulary during hydration:
RDW occasionally introduces new values, and a strict cast would turn that
into a crash. Treat `exhaustive` as a hint, not a contract.

## Schema regeneration

Field enums and record classes live under `src/Fields/` and `src/Records/`.
They are generated from the curated override classes under
`src/Schema/Overrides/` and validated against checked-in metadata
snapshots under `metadata/`.

To refresh after a change to overrides (or after pulling a new RDW
schema snapshot):

```bash
composer rdw:generate
```

Generation fails loudly when an RDW metadata field has no override or
when an override points at a field RDW no longer exposes — that is the
public API stability contract from the implementation plan.

## Testing

```bash
composer test
```

The default suite is fully offline and uses mocked HTTP. To run live tests
against `opendata.rdw.nl`, add your own tests behind an environment guard;
the package does not ship live tests in the default suite.

### Faking RDW in your own tests

`NiekNijland\RDW\Testing\FakeRdw` is a drop-in `Rdw` for application tests. It
runs the real query builder and hydrator against seeded rows, so no HTTP is
made. Seed raw Socrata rows (RDW field keys) per dataset; query parameters are
recorded for assertions but do not filter the returned rows.

```php
use NiekNijland\RDW\Datasets\DatasetId;
use NiekNijland\RDW\Testing\FakeRdw;

$rdw = (new FakeRdw())
    ->withRegisteredVehicles([
        ['kenteken' => 'AB123C', 'merk' => 'HONDA', 'handelsbenaming' => 'CBR600RR', 'cilinderinhoud' => '599'],
    ])
    ->withRegisteredVehicleFuels([
        ['kenteken' => 'AB123C', 'nettomaximumvermogen' => '88'],
    ]);

$record = $rdw->registeredVehicles()->where(RegisteredVehicleField::Brand, 'HONDA')->first();
// $record->licensePlate === 'AB123C', $record->engineDisplacement === 599

// Simulate a transient failure, then recover:
$rdw->failWith(DatasetId::RegisteredVehicles);          // throws RdwException on next query
$rdw->clearFailure(DatasetId::RegisteredVehicles);

// Assert on what was requested:
$rdw->recordedRequests(); // [['dataset' => 'm9d7-ebf2', 'query' => [...]], ...]
```

In a service container, bind it for the test: `$this->app->instance(Rdw::class, $rdw)`.

## Architecture

```
src/
  Rdw.php                      # entry point
  Http/
    Configuration.php          # app token, user agent, timeout
    SocrataClient.php          # thin Guzzle wrapper
  Datasets/
    DatasetId.php              # backed enum of in-scope RDW dataset ids
    DatasetRegistry.php
  Schema/
    CastType.php               # how a single raw value is transformed
    FieldDescriptor.php
    DatasetSchema.php
    SchemaRegistry.php
    Overrides/                 # the canonical English name + cast map (one per dataset)
  Fields/                      # GENERATED — enum cases for typed queries
  Records/                     # GENERATED — typed value objects per dataset
    Hydrator.php
    ValueCaster.php
  Query/
    QueryBuilder.php
    SortDirection.php
  Relations/
    Relations.php              # typed loaders (fuelsFor, axlesFor, …)
  Generator/
    EnumGenerator.php          # produces src/Fields and src/Records from overrides
    SchemaSnapshot.php         # reads metadata/{id}.json
  Exceptions/
    RdwException.php           # base
    HttpException.php
    RateLimitException.php
    DatasetNotFoundException.php
    MissingFieldOverrideException.php
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for what has changed recently.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
