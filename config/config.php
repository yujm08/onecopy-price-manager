<?php
define('BASE_URL', '/onecopy-price-manager');

define('APP_ENV', 'development');

define('CRON_SECRET_KEY', '4a313e6d708ab42f7a46ff079ac9678e2c8d0608087aca72bcad5f1901876bb8');

if (APP_ENV === 'production') {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../logs/php_error.log');
} else {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

function h($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
