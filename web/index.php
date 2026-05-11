<?php
/**
 * Craft web bootstrap file
 */

// Load shared bootstrap
require dirname(__DIR__) . '/bootstrap.php';

// Every request should be a CP request, except for the API and sitemap.xml
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$isSitemap = str_starts_with($uri, '/sitemap') && str_ends_with($uri, '.xml');
define('CRAFT_CP', $uri !== '/api' && !$isSitemap);

// Load and run Craft
/** @var craft\web\Application $app */
$app = require CRAFT_VENDOR_PATH . '/craftcms/cms/bootstrap/web.php';
$app->run();
