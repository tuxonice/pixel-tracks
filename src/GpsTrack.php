<?php

namespace PixelTrack;

use phpGPX\Models\GpxFile;
use phpGPX\Models\Point;
use phpGPX\phpGPX;

class GpsTrack
{
    private phpGPX $gpx;

    private GpxFile $gpxFile;

    private array $data;

    private float $totalDistance = 0.0;

    private float $vDistance = 0.0;


    public function __construct(string $filename)
    {
        $this->gpx = new phpGPX();
        $this->gpxFile = $this->gpx->load($filename);
        $this->process();
    }

    public function getPoints(): array
    {
        return $this->data;
    }

    public function getJsonPoints(): string
    {
        return json_encode($this->data);
    }

    private function process(): array
    {
        foreach ($this->gpxFile->tracks as $track) {
            foreach ($track->segments as $segment) {
                $carryDistance = 0.0;
                foreach ($segment->points as $key => $point) {
                    $startPoint = $segment->points[$key];
                    if (!isset($segment->points[$key + 1])) {
                        break;
                    }
                    $endPoint = $segment->points[$key + 1];
                    $parseDiffPoints = $this->parseDiffPoints($startPoint, $endPoint, $carryDistance);
                    $this->data[] = $parseDiffPoints;
                    $this->vDistance += $parseDiffPoints['vDistance'] > 0 ? $parseDiffPoints['vDistance'] : 0.0;
                }
            }
        }

        $this->totalDistance = $carryDistance;

        return $this->data;
    }

    private function parseDiffPoints(Point $start, Point $end, &$carryDistance): array
    {
        $hDistance = $this->distance($start, $end);
        //$time = $end->time->getTimestamp() - $start->time->getTimestamp();
        $vDistance = $end->elevation - $start->elevation;
        $carryDistance += $hDistance;
        return [
            //'startTime' => $start->time->format('Y-m-d H:i:s'),
            'latitude' => $start->latitude,
            'longitude' => $start->longitude,
            'distance' => $hDistance,
            //'time' => $time,
            'elevation' => $start->elevation,
            //'hSpeed' => $time ? $hDistance / $time : 0.0,
            //'vSpeed' => $time ? $vDistance / $time : 0.0,
            'totalDistance' =>  $carryDistance,
            'vDistance' => $vDistance,
        ];
    }

    // From https://www.movable-type.co.uk/scripts/latlong.html
    private function distance(Point $start, Point $end): float
    {
        $R = 6371e3; // metres
        $fi1 = $start->latitude * M_PI / 180; // φ, λ in radians
        $fi2 = $end->latitude * M_PI / 180;
        $deltaFi = ($end->latitude - $start->latitude) * M_PI / 180;
        $deltaLambda = ($end->longitude - $start->longitude) * M_PI / 180;

        $a = sin($deltaFi / 2) * sin($deltaFi / 2) +
            cos($fi1) * cos($fi2) *
            sin($deltaLambda / 2) * sin($deltaLambda / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $R * $c; // in metres
    }

    public function getInfo(): array
    {
        return [
            'points' => count($this->data),
            'totalDistance' => sprintf("%.02f", $this->totalDistance / 1000),
            'totalHeight' => sprintf("%.02f", $this->vDistance),
        ];
    }
}
