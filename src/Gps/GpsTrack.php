<?php

namespace App\Gps;

use phpGPX\Models\GpxFile;
use phpGPX\Models\Point;
use phpGPX\phpGPX;

class GpsTrack
{
    private phpGPX $gpx;
    private GpxFile $gpxFile;

    /** @var array<int,array<string,mixed>> */
    private array $data = [];

    private float $totalDistance = 0.0;
    private float $vDistance = 0.0;
    private ?\DateTimeImmutable $recordedAt = null;

    public function __construct()
    {
        $this->gpx = new phpGPX();
    }

    /** @return array<int,array<string,mixed>> */
    public function getPoints(): array
    {
        return $this->data;
    }

    public function getJsonPoints(): string
    {
        return (string) json_encode($this->data);
    }

    public function process(string $filename): void
    {
        $this->data = [];
        $this->recordedAt = null;
        $this->gpxFile = $this->gpx->load($filename);

        $carryHDistance = 0.0;
        foreach ($this->gpxFile->tracks as $track) {
            foreach ($track->segments as $segment) {
                $carryHDistance = 0.0;
                $this->processPoints($segment->points, $carryHDistance);
                $this->recordedAt ??= $this->earliestPointTime($segment->points);
            }
        }

        foreach ($this->gpxFile->routes as $route) {
            $carryHDistance = 0.0;
            $this->processPoints($route->points, $carryHDistance);
            $this->recordedAt ??= $this->earliestPointTime($route->points);
        }

        $this->recordedAt ??= $this->metadataTime();

        $this->totalDistance = $carryHDistance;
    }

    /** The time recorded in the GPX file for this track, if any: the first track/route point's
     * time, falling back to the file's metadata time. Null when the file has no time data at all. */
    public function getRecordedAt(): ?\DateTimeImmutable
    {
        return $this->recordedAt;
    }

    /** @param Point[] $points */
    private function earliestPointTime(array $points): ?\DateTimeImmutable
    {
        $time = ($points[0] ?? null)?->time;

        return $time !== null ? \DateTimeImmutable::createFromMutable($time) : null;
    }

    private function metadataTime(): ?\DateTimeImmutable
    {
        $time = $this->gpxFile->metadata?->time;

        return $time !== null ? \DateTimeImmutable::createFromMutable($time) : null;
    }

    /** @param Point[] $points */
    private function processPoints(array $points, float &$carryHDistance): void
    {
        foreach ($points as $key => $point) {
            if (!isset($points[$key + 1])) {
                break;
            }
            $endPoint = $points[$key + 1];
            $parseDiffPoints = $this->parseDiffPoints($point, $endPoint, $carryHDistance);
            $this->data[] = $parseDiffPoints;
            $this->vDistance += $parseDiffPoints['vDistance'] > 0 ? $parseDiffPoints['vDistance'] : 0.0;
        }
    }

    /** @return array<string,mixed> */
    private function parseDiffPoints(Point $start, Point $end, float &$carryHDistance): array
    {
        $hDistance = $this->distance($start, $end);
        $vDistance = abs($hDistance) >= 1 ? $end->elevation - $start->elevation : 0.0;
        $carryHDistance += $hDistance;

        return [
            'latitude' => $start->latitude,
            'longitude' => $start->longitude,
            'distance' => $hDistance,
            'elevation' => $start->elevation,
            'totalDistance' => $carryHDistance,
            'vDistance' => $vDistance,
        ];
    }

    // From https://www.movable-type.co.uk/scripts/latlong.html
    private function distance(Point $start, Point $end): float
    {
        $R = 6371e3;
        $fi1 = $start->latitude * M_PI / 180;
        $fi2 = $end->latitude * M_PI / 180;
        $deltaFi = ($end->latitude - $start->latitude) * M_PI / 180;
        $deltaLambda = ($end->longitude - $start->longitude) * M_PI / 180;

        $a = sin($deltaFi / 2) * sin($deltaFi / 2) +
            cos($fi1) * cos($fi2) *
            sin($deltaLambda / 2) * sin($deltaLambda / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $R * $c;
    }

    /** @return array{points:int,totalDistance:string,totalHeight:string} */
    public function getInfo(): array
    {
        return [
            'points' => count($this->data),
            'totalDistance' => sprintf('%.02f', $this->totalDistance / 1000),
            'totalHeight' => sprintf('%.02f', $this->vDistance),
        ];
    }
}
