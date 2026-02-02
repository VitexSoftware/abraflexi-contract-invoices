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

    public function report(): array
    {
        $status = 'success';
        $message = 'Liabilities generation completed';
        $metrics = [
            'processed_advances' => \count($this->zalohy),
            'created_liabilities' => \count($this->zavazky),
        ];

        // Check for errors in status messages
        $statusMessages = $this->getStatusMessages();

        foreach ($statusMessages as $statusMsg) {
            if ($statusMsg['type'] === 'error') {
                $status = 'error';
                $message = 'Liabilities generation failed: '.$statusMsg['message'];

                break;
            }

            if ($statusMsg['type'] === 'warning' && $status !== 'error') {
                $status = 'warning';
                $message = 'Liabilities generation completed with warnings';
            }
        }

        return [
            'producer' => 'AbraFlexi Contracts2Liabilities',
            'status' => $status,
            'timestamp' => (new \DateTime())->format('c'),
            'message' => $message,
            'artifacts' => [
                'liabilities' => array_values($this->zavazky),
            ],
            'metrics' => $metrics,
        ];
    }
}
