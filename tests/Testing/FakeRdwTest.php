<?php

declare(strict_types=1);

namespace NiekNijland\RDW\Tests\Testing;

use NiekNijland\RDW\Datasets\DatasetId;
use NiekNijland\RDW\Exceptions\RdwException;
use NiekNijland\RDW\Fields\RegisteredVehicleField;
use NiekNijland\RDW\Testing\FakeRdw;
use NiekNijland\RDW\Testing\FakeSocrataClient;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FakeRdw::class)]
#[CoversClass(FakeSocrataClient::class)]
final class FakeRdwTest extends TestCase
{
    public function test_hydrates_seeded_rows_through_the_real_query_builder(): void
    {
        $rdw = (new FakeRdw())->withRegisteredVehicles([
            ['kenteken' => 'AB123C', 'merk' => 'HONDA', 'handelsbenaming' => 'CBR600RR', 'cilinderinhoud' => '599'],
        ]);

        $record = $rdw->registeredVehicles()
            ->where(RegisteredVehicleField::Brand, 'HONDA')
            ->first();

        self::assertNotNull($record);
        self::assertSame('AB123C', $record->licensePlate);
        self::assertSame('CBR600RR', $record->commercialName);
        self::assertSame(599, $record->engineDisplacement);
    }

    public function test_serves_each_dataset_independently(): void
    {
        $rdw = (new FakeRdw())
            ->withRegisteredVehicles([['kenteken' => 'AB123C']])
            ->withRegisteredVehicleFuels([['kenteken' => 'AB123C', 'nettomaximumvermogen' => '88']]);

        self::assertSame('88', $rdw->registeredVehicleFuels()->first()?->netMaximumPower);
        self::assertSame('AB123C', $rdw->registeredVehicles()->first()?->licensePlate);
    }

    public function test_returns_empty_when_a_dataset_has_no_seeded_rows(): void
    {
        $rdw = new FakeRdw();

        self::assertNull($rdw->registeredVehicles()->first());
        self::assertSame([], $rdw->registeredVehicles()->get());
    }

    public function test_records_requests_for_assertions(): void
    {
        $rdw = (new FakeRdw())->withRegisteredVehicles([]);

        $rdw->registeredVehicles()->where(RegisteredVehicleField::Brand, 'HONDA')->get();

        self::assertCount(1, $rdw->recordedRequests());
        self::assertSame(DatasetId::RegisteredVehicles->value, $rdw->recordedRequests()[0]['dataset']);
    }

    public function test_simulates_a_dataset_failure_until_cleared(): void
    {
        $rdw = (new FakeRdw())
            ->withRegisteredVehicles([['kenteken' => 'AB123C']])
            ->failWith(DatasetId::RegisteredVehicles);

        try {
            $rdw->registeredVehicles()->get();
            self::fail('Expected a RdwException.');
        } catch (RdwException) {
            // expected
        }

        $rdw->clearFailure(DatasetId::RegisteredVehicles);

        self::assertSame('AB123C', $rdw->registeredVehicles()->first()?->licensePlate);
    }
}
