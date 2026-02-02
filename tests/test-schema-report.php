#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Test script to demonstrate schema-compliant JSON report output.
 *
 * @author Vítězslav Dvořák <vitex@vitexsoftware.com>
 * @copyright 2018-2025 Spoje.Net
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Example of a schema-compliant report
$report = [
    'producer' => 'AbraFlexi Contracts2Invoices Test',
    'status' => 'success',
    'timestamp' => (new DateTime())->format('c'),
    'message' => 'Test execution completed successfully',
    'artifacts' => [
        'invoices' => [
            'INV001' => 'Test Invoice 1',
            'INV002' => 'Test Invoice 2'
        ]
    ],
    'metrics' => [
        'processed_contracts' => 5,
        'created_invoices' => 2,
        'failed_contracts' => 0,
        'execution_time_seconds' => 1.2
    ]
];

echo json_encode($report, JSON_PRETTY_PRINT) . "\n";

// Validation against schema would be:
// validate-json --schema https://raw.githubusercontent.com/VitexSoftware/php-vitexsoftware-multiflexi-core/refs/heads/main/schema/report.json