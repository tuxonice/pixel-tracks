<?php

use PixelTrack\GpsTrack;

require '../vendor/autoload.php';

$app = new GpsTrack('19-mar-2023.gpx');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <!-- loading jsPanel css -->
    <link href="https://cdn.jsdelivr.net/npm/jspanel4@4.12.0/dist/jspanel.css" rel="stylesheet">
    <!-- jsPanel JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/jspanel4@4.12.0/dist/jspanel.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css"
          integrity="sha512-xodZBNTC5n17Xt2atTPuE1HxjVMSvLVW9ocqUKLsCC5CXdbqCmblAshOMAS6/keqq/sMZMZ19scR4PsZChSR7A=="
          crossorigin=""/>
    <!-- Make sure you put this AFTER Leaflet's CSS -->
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"
            integrity="sha512-XQoYMqMTK8LvdxXYG3nZ448hOEQiglfqkJs1NOQV44cWnUrBc8PkAOcXy20w0vlaXaVUearIOBhiXZ5V3ynxwA=="
            crossorigin=""></script>
    <link rel="stylesheet" href="css/style.css"/>
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
<div id="samplecontent" style="display: none;">
    <ul class="form-style-1">
        <li>
            <label>Upload new track</label>
            <input type="file" id="value" name="value" class="field-long"/>
        </li>
        <li>
            <input type="button" value="Upload" onclick="go();"/>
        </li>
        <li>Lat: <span id="lat"></span></li>
        <li>Lng: <span id="lng"></span></li>
    </ul>
</div>
<script
        src="https://code.jquery.com/jquery-3.6.0.slim.min.js"
        integrity="sha256-u7e5khyithlIdTpu22PHhENmPcRdFiHRjhAuHcs05RI="
        crossorigin="anonymous">
</script>
<script>
    let jsonData = JSON.parse('<?php echo($app->getJsonPoints()) ?>');
    let latlngs = jsonData.map((point) =>[point.latitude, point.longitude]);

    let mymap = L.map('mapid').setView([37.0146, -7.9331], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 18,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    }).addTo(mymap);

    let polyline = L.polyline(latlngs, {color: 'red'}).addTo(mymap);
    mymap.fitBounds(polyline.getBounds());

    $(function() {
        jsPanel.create({
            position: {my: "right-top", at: "right-top", offsetY: 15, offsetX: -15},
            content: $("#samplecontent").html(),
            contentSize: {width: 300, height: 280},
            headerTitle: 'Control panel',
            theme: 'rebeccapurple',
            callback: function (panel) {
                $("#samplecontent").remove();
            }
        });
    })
</script>
</body>
</html>
