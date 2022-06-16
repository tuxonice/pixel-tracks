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
    <script src="https://cdn.amcharts.com/lib/5/index.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/xy.js"></script>
    <script src="https://cdn.amcharts.com/lib/5/themes/Animated.js"></script>
    <style>
        body {
            margin: 0;
            padding: 0;
        }
    </style>
    <style>
        #chartdiv {
            width: 100%;
            height: 500px;
            max-width: 100%
        }
    </style>
    <!-- Chart code -->
    <script>
        am5.ready(function() {

            // Create root element
            // https://www.amcharts.com/docs/v5/getting-started/#Root_element
            var root = am5.Root.new("chartdiv");


            // Set themes
            // https://www.amcharts.com/docs/v5/concepts/themes/
            root.setThemes([
                am5themes_Animated.new(root)
            ]);


            // Create chart
            // https://www.amcharts.com/docs/v5/charts/xy-chart/
            var chart = root.container.children.push(am5xy.XYChart.new(root, {
                panX: true,
                panY: true,
                wheelX: "panX",
                wheelY: "zoomX",
                pinchZoomX:true
            }));


            // Add cursor
            // https://www.amcharts.com/docs/v5/charts/xy-chart/cursor/
            var cursor = chart.set("cursor", am5xy.XYCursor.new(root, {
                behavior: "none"
            }));
            cursor.lineY.set("visible", false);

            function generateDatas() {

                let jsonData = JSON.parse('<?php echo($app->getJsonPoints()) ?>');
                return datapoints = jsonData.map(function(point) {
                    return {distance: point.totalDistance, value: point.elevation}
                });
            }


            // Create axes
            // https://www.amcharts.com/docs/v5/charts/xy-chart/axes/
            var xAxis = chart.xAxes.push(am5xy.ValueAxis.new(root, {
                renderer: am5xy.AxisRendererX.new(root, {}),
                tooltip: am5.Tooltip.new(root, {})
            }));

            var yAxis = chart.yAxes.push(am5xy.ValueAxis.new(root, {
                renderer: am5xy.AxisRendererY.new(root, {})
            }));


            // Add series
            // https://www.amcharts.com/docs/v5/charts/xy-chart/series/
            var series = chart.series.push(am5xy.LineSeries.new(root, {
                name: "Series",
                xAxis: xAxis,
                yAxis: yAxis,
                valueYField: "value",
                valueXField: "distance",
                tooltip: am5.Tooltip.new(root, {
                    labelText: "{valueY}"
                })
            }));


            // Add scrollbar
            // https://www.amcharts.com/docs/v5/charts/xy-chart/scrollbars/
            chart.set("scrollbarX", am5.Scrollbar.new(root, {
                orientation: "horizontal"
            }));


            // Set data
            var data = generateDatas();
            series.data.setAll(data);


            // Make stuff animate on load
            // https://www.amcharts.com/docs/v5/concepts/animations/
            series.appear(data.count);
            chart.appear(data.count, 100);

        }); // end am5.ready()
    </script>
</head>
<body>
<div id="mapdata">
    <div id="chartdiv"></div>
</div>
</body>
</html>
