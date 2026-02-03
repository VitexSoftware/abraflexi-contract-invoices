#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * This file is part of the Contract Invoices for AbraFlexi.
 *
 * https://github.com/VitexSoftware/abraflexi-contract-invoices
 *
 * (c) Vítězslav Dvořák <http://vitexsoftware.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

require_once __DIR__.'/../vendor/autoload.php';

// Example of a schema-compliant report
$report = [
    'producer' => 'AbraFlexi Contracts2Invoices Test',
    'status' => 'success',
    'timestamp' => (new DateTime())->format('c'),
    'message' => 'Test execution completed successfully',
    'artifacts' => [
        'invoices' => [
            'INV001' => 'Test Invoice 1',
            'INV002' => 'Test Invoice 2',
        ],
    ],
    'metrics' => [
        'processed_contracts' => 5,
        'created_invoices' => 2,
        'failed_contracts' => 0,
        'execution_time_seconds' => 1.2,
    ],
];

echo json_encode($report, \JSON_PRETTY_PRINT)."\n";

// Validation against schema would be:
// validate-json --schema https://raw.githubusercontent.com/VitexSoftware/php-vitexsoftware-multiflexi-core/refs/heads/main/schema/report.json
