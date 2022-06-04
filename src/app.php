<?php

use phpGPX\phpGPX;

require '../vendor/autoload.php';

$gpx = new phpGPX();

$file = $gpx->load('../data/29-mai-2022.gpx');

$data = [];
foreach ($file->tracks as $track)
{
    // Statistics for whole track
    $track->stats->toArray();


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

