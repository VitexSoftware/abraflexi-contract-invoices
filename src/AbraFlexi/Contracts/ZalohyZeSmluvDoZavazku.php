<?php

/**
 * System.Spoje.Net - Converter of advance invoices to liabilities.
 *
 * @author     Vítězslav Dvořák <vitex@arachne.cz>
 * @copyright  2015 Spoje.Net
 */
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

namespace AbraFlexi\Contracts;

/**
 * Description of ZalohyZeSmluvDoZavazku.
 *
 * @author vitex
 */
class ZalohyZeSmluvDoZavazku extends \AbraFlexi\DodavatelskaSmlouva
{
    public array $zalohy = [];
    public array $zavazky = [];

    /**
     * Items from advance invoices.
     */
    private array $zalohyPolozky = [];
    private \AbraFlexi\FakturaPrijata $invoicer;

    public function __construct($init = null, $options = [])
    {
        parent::__construct($init, $options);
        $this->invoicer = new \AbraFlexi\FakturaPrijata();
    }

    /**
     * Load advance invoices.
     */
    public function nactiZalohoveFaktury(): void
    {
        $this->zalohy = (array) $this->invoicer->getColumnsFromAbraFlexi(
            '*',
            ['typDokl' => \AbraFlexi\Code::ensure(\Ease\Shared::cfg('LIABILITY_INVOICE_TYPE')), 'limit' => 0, 'relations' => 'polozkyDokladu', 'smlouva' => 'is not empty'],
            'id',
        );
    }

    public function zkonvertujZalohyNaZavazky(): void
    {
        foreach ($this->zalohy as $id => $zaloha) {
            $this->zavazky[$id] = $this->convert(new \AbraFlexi\FakturaPrijata($zaloha));
        }
    }

    public function ulozZavazky()
    {
        $success = [];

        foreach ($this->zavazky as $id => $zavazek) {
            $zavazekInserted = $zavazek->insertToAbraFlexi();

            if ($zavazek->lastResponseCode === 201) {
                $zavazekID = (int) $zavazekInserted[0]['id'];
                $zavazek->addStatusMessage(sprintf(
                    _('Liability %d created'),
                    $zavazekID,
                ));
                $success[$id] = $zavazekID;
            } else {
                unset($this->zalohy[$id]); // Not converted, will not be deleted
            }
        }

        if (\count($success)) {
            $this->addStatusMessage(sprintf(
                _('Generated %s other liabilities from %s advance invoices'),
                \count($success),
                \count($this->zalohy),
            ));
        }

        return $success;
    }

    /**
     * Delete advance invoices from which other liabilities were successfully created.
     */
    public function uklidZpracovaneZalohy(): void
    {
        $this->setEvidence('faktura-prijata');

        if (!empty($this->zalohy)) {
            foreach ($this->zalohy as $id => $zaloha) {
                if (!$this->deleteFromAbraFlexi((int) $zaloha['id'])) {
                    $this->addStatusMessage(sprintf(
                        _('Failed to delete advance invoice %s'),
                        $id,
                    ), 'warning');
                }
            }

            $this->addStatusMessage(
                _('Advance invoices converted to liabilities have been deleted'),
                'success',
            );
        }
    }

    /**
     * Convert advance invoice to liability.
     *
     * @param array $zaloha
     */
    public function convert($zaloha)
    {
        $engine = new \AbraFlexi\Bricks\Convertor($zaloha, new \AbraFlexi\Zavazek(['typDokl' => \AbraFlexi\Code::ensure(\Ease\Shared::cfg('LIABILITY_DOCUMENT_TYPE'))]));

        return $engine->conversion();
    }
    
    /**
     * Generate MultiFlexi report compliant with schema.
     * 
     * @return array Report data following the MultiFlexi report schema
     */
    public function report(): array
    {
        $advanceCount = \count($this->zalohy);
        $liabilityCount = \count($this->zavazky);
        $hasErrors = false;
        $hasWarnings = false;
        
        // Check for errors or warnings in status messages
        foreach ($this->getStatusMessages() as $message) {
            if (isset($message['type'])) {
                if ($message['type'] === 'error') {
                    $hasErrors = true;
                }
                if ($message['type'] === 'warning') {
                    $hasWarnings = true;
                }
            }
        }
        
        // Determine overall status
        if ($hasErrors) {
            $status = 'error';
            $message = 'Processing completed with errors';
        } elseif ($hasWarnings) {
            $status = 'warning';
            $message = 'Processing completed with warnings';
        } else {
            $status = 'success';
            $message = sprintf(
                'Successfully processed %d advance invoice(s) into %d liability/liabilities',
                $advanceCount,
                $liabilityCount
            );
        }
        
        $report = [
            'producer' => 'ZalohyZeSmluvDoZavazku',
            'status' => $status,
            'timestamp' => date('c'),
            'message' => $message,
            'metrics' => [
                'advance_invoices_processed' => $advanceCount,
                'liabilities_created' => $liabilityCount,
            ],
        ];
        
        return $report;
    }
}
