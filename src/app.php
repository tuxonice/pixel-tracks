<?php

use phpGPX\phpGPX;

require '../vendor/autoload.php';

$gpx = new phpGPX();

$file = $gpx->load('../data/29-mai-2022.gpx');



$data = [];
foreach ($file->tracks as $track)
{
    // Statistics for whole track
    $statistics = $track->stats->toArray();

    foreach ($track->segments as $segment)
    {
        foreach($segment->points as $key => $point) {

//            if($key % 2 === 0) {
//                continue;
//            }
            $data[] = [$point->latitude, $point->longitude];
        }
    }
}


// From https://www.movable-type.co.uk/scripts/latlong.html
function distance(float $lat1, float $long1, float $lat2, float $long2): float
{
    $R = 6371e3; // metres
    $fi1 = $lat1 * M_PI/180; // φ, λ in radians
    $fi2 = $lat2 * M_PI/180;
    $deltaFi = ($lat2 - $lat1) * M_PI/180;
    $deltaLambda = ($long2 - $long1) * M_PI/180;

    $a = sin($deltaFi/2) * sin($deltaFi/2) +
        cos($fi1) * cos($fi2) *
        sin($deltaLambda/2) * sin($deltaLambda/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));

    return $R * $c; // in metres
}