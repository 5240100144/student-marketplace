<?php
// Application Configuration

$detectedRoot = realpath(__DIR__ . '/..') ?: dirname(__DIR__);
define('ROOT_PATH', str_replace('\\', '/', $detectedRoot));

// Lightweight .env file loader
function load_env($filePath) {
    if (!file_exists($filePath)) {
        return false;
    }

    $content = @file_get_contents($filePath);
    if ($content === false) {
        return false;
    }

    $lines = preg_split("/\r\n|\n|\r/", $content);
    if ($lines === false) {
        return false;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || str_starts_with($line, ';')) {
            continue;
        }

        if (str_starts_with($line, 'export ')) {
            $line = trim(substr($line, 7));
        }

        if (!str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) ||
            (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }

        if (!str_starts_with($value, '"') && !str_starts_with($value, "'") && str_contains($value, ' #')) {
            $parts = explode(' #', $value, 2);
            $value = trim($parts[0]);
        }

        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv("{$name}={$value}");
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }

    return true;
}

// Retrieve environment variable with fallback
function env($key, $default = null) {
    $val = getenv($key);
    if ($val !== false && $val !== '') {
        return $val;
    }
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        return $_ENV[$key];
    }
    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
        return $_SERVER[$key];
    }
    return $default;
}

load_env(ROOT_PATH . '/.env');

// Error reporting by environment
$appEnv = env('APP_ENV', 'development');
if ($appEnv === 'production') {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
} else {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
}

// Application settings
define('APP_NAME', 'CampusMarket');
define('APP_TAGLINE', 'Buy and sell textbooks, electronics, and essentials with fellow students');
define('APP_CURRENCY', 'GH₵');
define('APP_CURRENCY_CODE', 'GHS');

// Base URL detection
$envAppUrl = env('APP_URL');
if (!empty($envAppUrl)) {
    define('BASE_URL', rtrim($envAppUrl, '/'));
} else {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
        || (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] === 'on');

    $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '/');
    if (strpos($scriptDir, '\\') !== false) {
        $scriptDir = str_replace('\\', '/', $scriptDir);
    }

    $rootPath = preg_replace('#/(admin|config|database|includes|tests|uploads).*$#', '', $scriptDir);
    if ($rootPath === '/' || $rootPath === '\\' || $rootPath === '.') {
        $rootPath = '';
    }

    define('BASE_URL', rtrim($rootPath, '/'));
}

define('UPLOAD_DIR', ROOT_PATH . '/uploads/products');
define('UPLOAD_URL', BASE_URL . '/uploads/products');

// Session setup
if (session_status() === PHP_SESSION_NONE) {
    $sessDir = ROOT_PATH . '/database/sessions';
    if (!is_dir($sessDir)) {
        @mkdir($sessDir, 0755, true);
    }
    if (is_dir($sessDir) && is_writable($sessDir)) {
        @session_save_path($sessDir);
    }

    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');

    $isSecureConn = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
    if ($isSecureConn) {
        ini_set('session.cookie_secure', 1);
    }

    if (php_sapi_name() !== 'cli') {
        session_start();
    }
}

// Upload constraints
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5 MB
define('ALLOWED_MIME_TYPES', [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp'
]);
define('MAX_IMAGES_PER_PRODUCT', 5);

// Categories
define('PRODUCT_CATEGORIES', [
    'Books'            => 'Textbooks, revision guides & novels',
    'Electronics'      => 'Laptops, phones, chargers & gadgets',
    'Fashion'          => 'Clothing, footwear & campus accessories',
    'Hostel Essentials'=> 'Bedding, kettles, fans & storage',
    'Stationery'       => 'Calculators, notebooks & art tools',
    'Other'            => 'Miscellaneous student essentials'
]);

// Conditions
define('PRODUCT_CONDITIONS', ['New', 'Second-hand']);

// Notification settings
define('NOTIFY_EMAIL_FROM', env('NOTIFY_EMAIL_FROM', 'notifications@campusmarket.local'));
define('ENABLE_REAL_EMAIL', filter_var(env('ENABLE_REAL_EMAIL', false), FILTER_VALIDATE_BOOLEAN));
define('NOTIFICATION_LOG_FILE', ROOT_PATH . '/database/notifications.log');
