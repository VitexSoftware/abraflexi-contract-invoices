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

use AbraFlexi\Contracts\ZalohyZeSmluvDoZavazku;
use AbraFlexi\Contracts\ZalohyZeSmluvDoPohledavek;
use PHPUnit\Framework\TestCase;

/**
 * Test schema-compliant report generation.
 */
class SchemaReportTest extends TestCase
{
    private ZalohyZeSmluvDoZavazku $liabilities;
    private ZalohyZeSmluvDoPohledavek $receivables;

    protected function setUp(): void
    {
        $this->liabilities = new ZalohyZeSmluvDoZavazku();
        $this->receivables = new ZalohyZeSmluvDoPohledavek();
    }

    public function testLiabilitiesReportStructure(): void
    {
        $report = $this->liabilities->report();

        // Test required fields according to schema
        $this->assertIsArray($report);
        $this->assertArrayHasKey('producer', $report);
        $this->assertArrayHasKey('status', $report);
        $this->assertArrayHasKey('timestamp', $report);
        
        // Test optional fields that our implementation includes
        $this->assertArrayHasKey('message', $report);
        $this->assertArrayHasKey('artifacts', $report);
        $this->assertArrayHasKey('metrics', $report);
    }

    public function testLiabilitiesReportValues(): void
    {
        $report = $this->liabilities->report();

        // Test producer field
        $this->assertIsString($report['producer']);
        $this->assertEquals('AbraFlexi Contracts2Liabilities', $report['producer']);

        // Test status field (enum: success, error, warning)
        $this->assertIsString($report['status']);
        $this->assertContains($report['status'], ['success', 'error', 'warning']);

        // Test timestamp is valid ISO8601 format
        $this->assertIsString($report['timestamp']);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/',
            $report['timestamp']
        );

        // Test message is string
        $this->assertIsString($report['message']);

        // Test artifacts structure
        $this->assertIsArray($report['artifacts']);
        $this->assertArrayHasKey('liabilities', $report['artifacts']);

        // Test metrics structure
        $this->assertIsArray($report['metrics']);
        $this->assertArrayHasKey('processed_advances', $report['metrics']);
        $this->assertArrayHasKey('created_liabilities', $report['metrics']);
    }

    public function testReceivablesReportStructure(): void
    {
        $report = $this->receivables->report();

        // Test required fields according to schema
        $this->assertIsArray($report);
        $this->assertArrayHasKey('producer', $report);
        $this->assertArrayHasKey('status', $report);
        $this->assertArrayHasKey('timestamp', $report);
        
        // Test optional fields that our implementation includes
        $this->assertArrayHasKey('message', $report);
        $this->assertArrayHasKey('artifacts', $report);
        $this->assertArrayHasKey('metrics', $report);
    }

    public function testReceivablesReportValues(): void
    {
        $report = $this->receivables->report();

        // Test producer field
        $this->assertIsString($report['producer']);
        $this->assertEquals('AbraFlexi Contracts2Receivables', $report['producer']);

        // Test status field (enum: success, error, warning)
        $this->assertIsString($report['status']);
        $this->assertContains($report['status'], ['success', 'error', 'warning']);

        // Test timestamp is valid ISO8601 format
        $this->assertIsString($report['timestamp']);
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/',
            $report['timestamp']
        );

        // Test message is string
        $this->assertIsString($report['message']);

        // Test artifacts structure
        $this->assertIsArray($report['artifacts']);
        $this->assertArrayHasKey('receivables', $report['artifacts']);

        // Test metrics structure
        $this->assertIsArray($report['metrics']);
        $this->assertArrayHasKey('processed_advances', $report['metrics']);
        $this->assertArrayHasKey('created_receivables', $report['metrics']);
    }

    public function testReportJsonSerializable(): void
    {
        $liabilitiesReport = $this->liabilities->report();
        $receivablesReport = $this->receivables->report();

        // Test that reports can be JSON encoded
        $this->assertJson(json_encode($liabilitiesReport));
        $this->assertJson(json_encode($receivablesReport));

        // Test that JSON is valid and can be decoded back
        $decodedLiabilities = json_decode(json_encode($liabilitiesReport), true);
        $decodedReceivables = json_decode(json_encode($receivablesReport), true);

        $this->assertEquals($liabilitiesReport, $decodedLiabilities);
        $this->assertEquals($receivablesReport, $decodedReceivables);
    }

    public function testReportTimestampFormat(): void
    {
        $liabilitiesReport = $this->liabilities->report();
        $receivablesReport = $this->receivables->report();

        // Test timestamp format conforms to ISO8601 with timezone
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/',
            $liabilitiesReport['timestamp']
        );
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/',
            $receivablesReport['timestamp']
        );
    }

    public function testReportMetricsAreNumeric(): void
    {
        $liabilitiesReport = $this->liabilities->report();
        $receivablesReport = $this->receivables->report();

        // Test that metrics contain numeric values
        foreach ($liabilitiesReport['metrics'] as $key => $value) {
            $this->assertTrue(is_numeric($value), "Metric '{$key}' should be numeric");
        }

        foreach ($receivablesReport['metrics'] as $key => $value) {
            $this->assertTrue(is_numeric($value), "Metric '{$key}' should be numeric");
        }
    }
}