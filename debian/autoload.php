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
