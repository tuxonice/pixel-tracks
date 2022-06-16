<?php

require '../src/app.php';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css"
          integrity="sha512-xodZBNTC5n17Xt2atTPuE1HxjVMSvLVW9ocqUKLsCC5CXdbqCmblAshOMAS6/keqq/sMZMZ19scR4PsZChSR7A=="
          crossorigin=""/>
    <!-- Make sure you put this AFTER Leaflet's CSS -->
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"
            integrity="sha512-XQoYMqMTK8LvdxXYG3nZ448hOEQiglfqkJs1NOQV44cWnUrBc8PkAOcXy20w0vlaXaVUearIOBhiXZ5V3ynxwA=="
            crossorigin=""></script>
    <style>
        body {
            margin: 0;
            padding: 0;
        }

        #mapid {
            height: 100vh;
        }
    </style>
</head>
<body>
<div id="mapid"></div>
<script>

    let mymap = L.map('mapid').setView([37.0146, -7.9331], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(mymap);


    var latlngs = [
        <?php
        foreach($data as $point) {
        ?>
        [<?php echo($point[0]); ?>, <?php echo($point[1]); ?>],
        <?php
        }
        ?>
    ];
    var polyline = L.polyline(latlngs, {color: 'red'}).addTo(mymap);
    mymap.fitBounds(polyline.getBounds());
</script>
</body>
</html>