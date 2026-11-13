<?php

declare(strict_types=1);

// The plugin's files stop when WordPress is not loaded.
defined('ABSPATH') || define('ABSPATH', __DIR__ . '/');

require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/Support/wp-stubs.php';
