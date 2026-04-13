<?php
/**
 * General Configuration
 *
 * All of your system's general configuration settings go in here. You can see a
 * list of the available settings in vendor/craftcms/cms/src/config/GeneralConfig.php.
 *
 * @see \craft\config\GeneralConfig
 * @link https://craftcms.com/docs/5.x/reference/config/general.html
 */

use craft\config\GeneralConfig;
use craft\helpers\App;

return GeneralConfig::create()
    // Set the default week start day for date pickers (0 = Sunday, 1 = Monday, etc.)
    ->defaultWeekStartDay(1)
    // Prevent generated URLs from including "index.php"
    ->omitScriptNameInUrls()
    // Preload Single entries as Twig variables
    ->preloadSingles()
    // Prevent user enumeration attacks
    ->preventUserEnumeration()
    // Enable the Twig sandbox for system messages, etc.
    ->enableTwigSandbox()
    // Set the @webroot alias so the clear-caches command knows where to find CP resources.
    // @preview is used in preview target URL templates:
    //   {siteUrl}/{uri}?token={previewToken}&x-craft-live-preview=1
    ->aliases([
        '@webroot' => dirname(__DIR__) . '/web',
        '@preview' => App::env('PRIMARY_SITE_URL'),
    ])
    // Headless mode: Craft only serves the CP, action requests, and static assets.
    // All element routing and Twig template rendering is disabled.
    ->headlessMode()
;
