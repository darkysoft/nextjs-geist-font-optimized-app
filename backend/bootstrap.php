<?php

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Load configuration
$config = require_once __DIR__ . '/config/config.php';

// Autoload classes
spl_autoload_register(function ($class) {
    // Convert class name to file path
    $paths = [
        __DIR__ . '/src/Controllers/',
        __DIR__ . '/src/Services/'
    ];
    
    foreach ($paths as $path) {
        $file = $path . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

// Initialize error handler
$errorHandler = ErrorHandler::getInstance();

// Initialize logger
$logger = Logger::getInstance();

// Set timezone
date_default_timezone_set('UTC');

// CORS headers
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $config['cors']['allowed_origins'])) {
    header("Access-Control-Allow-Origin: $origin");
    header('Access-Control-Allow-Methods: ' . implode(', ', $config['cors']['allowed_methods']));
    header('Access-Control-Allow-Headers: ' . implode(', ', $config['cors']['allowed_headers']));
}

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('HTTP/1.1 204 No Content');
    exit();
}

// Check system requirements
$systemUtil = SystemUtil::getInstance();

// Verify Apache is installed
if (!$systemUtil->executeCommand('which apache2')['success']) {
    $errorHandler->sendError('Apache2 is not installed', 503);
    exit();
}

// Verify PHP has required extensions
$requiredExtensions = ['json', 'curl'];
foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $errorHandler->sendError("Required PHP extension '$ext' is not loaded", 503);
        exit();
    }
}

// Verify write permissions
$writableDirs = [
    $config['apache']['sites_available'],
    $config['apache']['sites_enabled']
];

foreach ($writableDirs as $dir) {
    if (!is_writable($dir)) {
        $errorHandler->sendError("Directory '$dir' is not writable", 503);
        exit();
    }
}

// Initialize system logger
$logger->info('Application started', [
    'php_version' => PHP_VERSION,
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'system_info' => $systemUtil->getSystemInfo()
]);

// Return config for use in application
return $config;
