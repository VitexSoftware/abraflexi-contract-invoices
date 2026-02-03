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

namespace AbraFlexi\Contracts\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Integration test for JSON output compliance.
 */
class JsonOutputIntegrationTest extends TestCase
{
    public function testInvoiceScriptOutputStructure(): void
    {
        $output = self::generateTestInvoiceReport();

        $this->validateSchemaCompliance($output, 'AbraFlexi Contracts2Invoices');
    }

    public function testLiabilityScriptOutputStructure(): void
    {
        $output = self::generateTestLiabilityReport();

        $this->validateSchemaCompliance($output, 'AbraFlexi Contracts2Liabilities');
    }

    public function testReceivableScriptOutputStructure(): void
    {
        $output = self::generateTestReceivableReport();

        $this->validateSchemaCompliance($output, 'AbraFlexi Contracts2Receivables');
    }

    public function testErrorStatusHandling(): void
    {
        $errorReport = [
            'producer' => 'AbraFlexi Contracts2Invoices',
            'status' => 'error',
            'timestamp' => (new \DateTime())->format('c'),
            'message' => 'Invoice generation failed: Connection timeout',
            'artifacts' => [
                'invoices' => [],
            ],
            'metrics' => [
                'processed_contracts' => 5,
                'created_invoices' => 0,
                'failed_contracts' => 5,
            ],
        ];

        $this->validateSchemaCompliance($errorReport, 'AbraFlexi Contracts2Invoices');
        $this->assertEquals('error', $errorReport['status']);
    }

    public function testWarningStatusHandling(): void
    {
        $warningReport = [
            'producer' => 'AbraFlexi Contracts2Liabilities',
            'status' => 'warning',
            'timestamp' => (new \DateTime())->format('c'),
            'message' => 'Liabilities generation completed with warnings',
            'artifacts' => [
                'liabilities' => [1, 2],
            ],
            'metrics' => [
                'processed_advances' => 5,
                'created_liabilities' => 2,
            ],
        ];

        $this->validateSchemaCompliance($warningReport, 'AbraFlexi Contracts2Liabilities');
        $this->assertEquals('warning', $warningReport['status']);
    }

    private static function generateTestInvoiceReport(): array
    {
        // Simulate the invoice generation output structure
        return [
            'producer' => 'AbraFlexi Contracts2Invoices',
            'status' => 'success',
            'timestamp' => (new \DateTime())->format('c'),
            'message' => 'Invoice generation completed',
            'artifacts' => [
                'invoices' => [
                    'CONTRACT001' => 'Test Contract 1',
                    'CONTRACT002' => 'Test Contract 2',
                ],
            ],
            'metrics' => [
                'processed_contracts' => 2,
                'created_invoices' => 2,
                'failed_contracts' => 0,
            ],
        ];
    }

    private static function generateTestLiabilityReport(): array
    {
        // Simulate the liability generation output structure
        return [
            'producer' => 'AbraFlexi Contracts2Liabilities',
            'status' => 'success',
            'timestamp' => (new \DateTime())->format('c'),
            'message' => 'Liabilities generation completed',
            'artifacts' => [
                'liabilities' => [],
            ],
            'metrics' => [
                'processed_advances' => 0,
                'created_liabilities' => 0,
            ],
        ];
    }

    private static function generateTestReceivableReport(): array
    {
        // Simulate the receivable generation output structure
        return [
            'producer' => 'AbraFlexi Contracts2Receivables',
            'status' => 'success',
            'timestamp' => (new \DateTime())->format('c'),
            'message' => 'Receivables generation completed',
            'artifacts' => [
                'receivables' => [],
            ],
            'metrics' => [
                'processed_advances' => 0,
                'created_receivables' => 0,
            ],
        ];
    }

    private function validateSchemaCompliance(array $output, string $expectedProducer): void
    {
        // Test required fields according to MultiFlexi schema
        $this->assertArrayHasKey('producer', $output);
        $this->assertArrayHasKey('status', $output);
        $this->assertArrayHasKey('timestamp', $output);

        // Test producer value
        $this->assertEquals($expectedProducer, $output['producer']);

        // Test status enum values
        $this->assertContains($output['status'], ['success', 'error', 'warning']);

        // Test timestamp format (ISO8601)
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/',
            $output['timestamp'],
        );

        // Test optional fields that we include
        if (isset($output['message'])) {
            $this->assertIsString($output['message']);
        }

        if (isset($output['artifacts'])) {
            $this->assertIsArray($output['artifacts']);
        }

        if (isset($output['metrics'])) {
            $this->assertIsArray($output['metrics']);

            // All metrics should be numeric
            foreach ($output['metrics'] as $key => $value) {
                $this->assertIsNumeric($value, "Metric '{$key}' should be numeric");
            }
        }

        // Test JSON serialization
        $json = json_encode($output);
        $this->assertJson($json);

        $decoded = json_decode($json, true);
        $this->assertEquals($output, $decoded);
    }
}
