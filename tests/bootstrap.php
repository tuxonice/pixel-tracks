<?php

use Dotenv\Dotenv;

error_reporting(E_ALL);
require(dirname(__DIR__).'/vendor/autoload.php');

$dotenv = Dotenv::createImmutable(dirname(__DIR__), '.env.test');
$dotenv->load();

date_default_timezone_set($_ENV['TIMEZONE']);


