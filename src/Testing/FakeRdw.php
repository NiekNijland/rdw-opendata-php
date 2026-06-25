<?php

declare(strict_types=1);

namespace NiekNijland\RDW\Testing;

use NiekNijland\RDW\Datasets\DatasetId;
use NiekNijland\RDW\Exceptions\HttpException;
use NiekNijland\RDW\Rdw;
use Throwable;

/**
 * Test double for {@see Rdw}. Backed by a {@see FakeSocrataClient}, so the real
 * query builder and hydrator run against seeded rows without any HTTP. Seed
 * rows per dataset, simulate failures, and assert on recorded requests.
 *
 *     $rdw = (new FakeRdw())->withRegisteredVehicles([
 *         ['kenteken' => 'AB123C', 'merk' => 'HONDA', 'handelsbenaming' => 'CBR600RR'],
 *     ]);
 */
final class FakeRdw extends Rdw
{
    private readonly FakeSocrataClient $fake;

    public function __construct()
    {
        $this->fake = new FakeSocrataClient();

        parent::__construct(http: $this->fake);
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function withRows(DatasetId $dataset, array $rows): self
    {
        $this->fake->setRows($dataset->value, $rows);

        return $this;
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function withRegisteredVehicles(array $rows): self
    {
        return $this->withRows(DatasetId::RegisteredVehicles, $rows);
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    public function withRegisteredVehicleFuels(array $rows): self
    {
        return $this->withRows(DatasetId::RegisteredVehicleFuels, $rows);
    }

    public function failWith(DatasetId $dataset, ?Throwable $exception = null): self
    {
        $this->fake->failWith($dataset->value, $exception ?? new HttpException('Faked RDW failure.', statusCode: 500));

        return $this;
    }

    public function clearFailure(DatasetId $dataset): self
    {
        $this->fake->clearFailure($dataset->value);

        return $this;
    }

    /**
     * @return list<array{dataset: string, query: array<string, scalar|null>}>
     */
    public function recordedRequests(): array
    {
        return $this->fake->recordedRequests();
    }
}
