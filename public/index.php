<?php

use PixelTrack\App;

require('../bootstrap.php');
$boot = App::getInstance();
$response = $boot->route();
$response->send();
