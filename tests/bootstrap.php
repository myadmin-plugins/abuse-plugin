<?php
/**
 * PHPUnit bootstrap file for myadmin-abuse-plugin tests.
 *
 * Sets up autoloading and defines stubs for external functions/constants
 * that the plugin depends on but are not available in a test environment.
 */

// Autoload the package's own classes via PSR-4
spl_autoload_register(function ($class) {
    $prefix = 'Detain\\MyAdminAbuse\\';
    $baseDir = __DIR__ . '/../src/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    if (file_exists($file)) {
        require $file;
    }
});

// ImapAbuseCheck is a global (non-namespaced) class — include it directly
require_once __DIR__ . '/../src/ImapAbuseCheck.php';
