<?php

namespace App\Tests\Unit\Gps;

use App\Gps\GpsTrack;
use PHPUnit\Framework\TestCase;

class GpsTrackTest extends TestCase
{
    /**
     * Independent reference implementation of the haversine great-circle distance,
     * used to check GpsTrack's own distance() math without calling it directly (it's private).
     */
    private static function haversineMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371e3;
        $fi1 = $lat1 * M_PI / 180;
        $fi2 = $lat2 * M_PI / 180;
        $deltaFi = ($lat2 - $lat1) * M_PI / 180;
        $deltaLambda = ($lon2 - $lon1) * M_PI / 180;

        $a = sin($deltaFi / 2) ** 2 + cos($fi1) * cos($fi2) * sin($deltaLambda / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    public function testProcessComputesPerPointDistanceAndElevationDiffs(): void
    {
        $track = new GpsTrack();
        $track->process(__DIR__ . '/../../Fixtures/gpx/valid-track.gpx');

        $legDistance = self::haversineMeters(0.0, 0.0, 0.0, 0.001);
        $points = $track->getPoints();

        // The fixture has 3 trkpt; process() drops the last one (it only emits a diff
        // per point that has a following point), so 2 diff entries are expected.
        self::assertCount(2, $points);

        self::assertSame(0.0, $points[0]['latitude']);
        self::assertSame(0.0, $points[0]['longitude']);
        self::assertSame(0.0, $points[0]['elevation']);
        self::assertEqualsWithDelta($legDistance, $points[0]['distance'], 0.01);
        self::assertEqualsWithDelta($legDistance, $points[0]['totalDistance'], 0.01);
        // ele goes 0.0 -> 10.0 across this leg.
        self::assertSame(10.0, $points[0]['vDistance']);

        self::assertSame(0.0, $points[1]['latitude']);
        self::assertEqualsWithDelta(0.001, $points[1]['longitude'], 1e-9);
        self::assertSame(10.0, $points[1]['elevation']);
        self::assertEqualsWithDelta($legDistance, $points[1]['distance'], 0.01);
        self::assertEqualsWithDelta($legDistance * 2, $points[1]['totalDistance'], 0.02);
        // ele goes 10.0 -> 5.0 across this leg: a descent, so the raw diff is negative.
        self::assertSame(-5.0, $points[1]['vDistance']);
    }

    public function testGetInfoSummarisesDistanceElevationGainAndPointCount(): void
    {
        $track = new GpsTrack();
        $track->process(__DIR__ . '/../../Fixtures/gpx/valid-track.gpx');

        $legDistance = self::haversineMeters(0.0, 0.0, 0.0, 0.001);
        $info = $track->getInfo();

        self::assertSame(2, $info['points']);
        self::assertSame(sprintf('%.02f', $legDistance * 2 / 1000), $info['totalDistance']);
        // Only the 0.0 -> 10.0 climb counts: the second leg is a descent, which the
        // source only counts when its diff is positive.
        self::assertSame('10.00', $info['totalHeight']);
    }

    public function testGetJsonPointsReturnsPointsAsJson(): void
    {
        $track = new GpsTrack();
        $track->process(__DIR__ . '/../../Fixtures/gpx/valid-track.gpx');

        self::assertSame(json_encode($track->getPoints()), $track->getJsonPoints());
    }
}
