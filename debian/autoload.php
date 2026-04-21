<?php
// Debian autoloader for abraflexi-contract-invoices
// Load dependency autoloaders
require_once '/usr/share/php/AbraFlexiBricks/autoload.php';


/**
 * Autoloader for AbraFlexi\Contracts namespace
 */
spl_autoload_register(function ($class) {
    if (strpos($class, 'AbraFlexi\\Contracts\\') === 0) {
        $file = '/usr/share/php/abraflexi-contract-invoices/' . str_replace('\\', '/', $class) . '.php';
        if (file_exists($file)) {
            require_once $file;
        }
    }
});

require_once '/usr/share/php/Composer/InstalledVersions.php';

(function (): void {
    $versions = [];
    foreach (\Composer\InstalledVersions::getAllRawData() as $d) {
        $versions = array_merge($versions, $d['versions'] ?? []);
    }
    $name    = 'unknown';
    $version = defined('APP_VERSION') ? APP_VERSION : '0.0.0';
    $versions[$name] = ['pretty_version' => $version, 'version' => $version,
        'reference' => null, 'type' => 'library', 'install_path' => __DIR__,
        'aliases' => [], 'dev_requirement' => false];
    \Composer\InstalledVersions::reload([
        'root' => ['name' => $name, 'pretty_version' => $version, 'version' => $version,
            'reference' => null, 'type' => 'project', 'install_path' => __DIR__,
            'aliases' => [], 'dev' => false],
        'versions' => $versions,
    ]);
})();
