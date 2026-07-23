<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// مسیرِ هسته‌ی لاراول را خودکار تشخیص بده تا در هر دو چیدمان کار کند:
//  • چیدمانِ استاندارد: هسته کنارِ پوشه‌ی public است ( ../vendor موجود ).
//  • چیدمانِ cPanel که ریشه‌ی دامنه public_html است و نمی‌شود Document Root را عوض کرد:
//    محتوای public را به public_html آورده‌اید و هسته را در ../starmah_core گذاشته‌اید.
$base = is_dir(__DIR__.'/../vendor') ? __DIR__.'/..' : __DIR__.'/../starmah_core';

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = $base.'/storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require $base.'/vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once $base.'/bootstrap/app.php';

$app->handleRequest(Request::capture());
