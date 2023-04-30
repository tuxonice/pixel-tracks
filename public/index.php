<?php

use PixelTrack\App;

require('../bootstrap.php');
$app = App::getInstance();
$response = $app->route();
$response->send();
