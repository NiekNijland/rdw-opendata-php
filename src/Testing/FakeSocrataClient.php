<?php

declare(strict_types=1);

namespace NiekNijland\RDW\Testing;

use NiekNijland\RDW\Exceptions\RdwException;
use NiekNijland\RDW\Http\SocrataClient;
use Throwable;

/**
 * In-memory {@see SocrataClient} for tests: serves pre-seeded rows per dataset
 * and never touches the network. Query parameters are recorded for assertions
 * but do not filter the returned rows — seed exactly what the call should see.
 */
final class FakeSocrataClient extends SocrataClient
{
    /** @var array<string, list<array<string, mixed>>> */
    private array $rows = [];

    /** @var array<string, Throwable> */
    private array $failures = [];

    /** @var list<array{dataset: string, query: array<string, scalar|null>}> */
    private array $recordedRequests = [];

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function setRows(string $datasetId, array $rows): void
    {
        $this->rows[$datasetId] = $rows;
    }

    public function failWith(string $datasetId, Throwable $exception): void
    {
        $this->failures[$datasetId] = $exception;
    }

    public function clearFailure(string $datasetId): void
    {
        unset($this->failures[$datasetId]);
    }

    /**
     * @return list<array{dataset: string, query: array<string, scalar|null>}>
     */
    public function recordedRequests(): array
    {
        return $this->recordedRequests;
    }

    /**
     * @param array<string, scalar|null> $query
     * @return list<array<string, mixed>>
     */
    public function getRows(string $datasetId, array $query = []): array
    {
        $this->recordedRequests[] = ['dataset' => $datasetId, 'query' => $query];

        if (isset($this->failures[$datasetId])) {
            throw $this->failures[$datasetId];
        }

        return $this->rows[$datasetId] ?? [];
    }

    public function getMetadata(string $datasetId): never
    {
        throw new RdwException('FakeSocrataClient does not support metadata fetches.');
    }
}
