<?php

use PixelTrack\CsvParse;

require '../vendor/autoload.php';

$app = new CsvParse('test.csv');
dd($app->getProcessedData());


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.6.0/Chart.min.js"
            integrity="sha512-ttHne44lbbucAUVjyStgbDTTqvNVQdIGN9gqZeai69i4OXSDNjlBd1tyCVXI/a/DqITpj9gXi84dcyG2vz4jhw=="
            crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    <style>
        body {
            margin: 0;
            padding: 0;
        }
    </style>
</head>
<body>
<div id="mapdata">
    <canvas id="myChart" width="400"></canvas>
</div>
<script>
    let jsonData = JSON.parse('<?php echo($app->getJsonProcessedData()) ?>');
    let datapoints = jsonData.map(function(row) {
        return {x: row., y: row.elevation}
    });
    const ctx = document.getElementById('myChart');
    const data = {
        datasets: [{
            label: 'Scatter Dataset',
            data: datapoints
        }]
    };
    const config = {
        type: 'line',
        data: data,
        options: {
            scales: {
                xAxes: [{
                    type: 'linear',
                    position: 'bottom'
                }]
            }
        }
    };

    const myChart = new Chart(ctx, config);
</script>
</body>
</html>