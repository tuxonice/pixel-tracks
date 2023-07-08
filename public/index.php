<?php

use PixelTrack\App;
use Symfony\Component\HttpFoundation\Request;

require('../bootstrap.php');
$app = App::getInstance();
$response = $app->route(Request::createFromGlobals());
$response->send();
