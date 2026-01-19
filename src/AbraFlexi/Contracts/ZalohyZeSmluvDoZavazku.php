<?php

/**
 * System.Spoje.Net - Konvertor zálohových faktur do závazků.
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
     * Polozky zalohovych faktur.
     */
    private array $zalohyPolozky = [];
    private \AbraFlexi\FakturaPrijata $invoicer;

    public function __construct($init = null, $options = [])
    {
        parent::__construct($init, $options);
        $this->invoicer = new \AbraFlexi\FakturaPrijata();
    }

    /**
     * Načte zálohové doklady.
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
                    _('Závazek %d vytvořen'),
                    $zavazekID,
                ));
                $success[$id] = $zavazekID;
            } else {
                unset($this->zalohy[$id]); // Nebyla prevedena nebude se mazat
            }
        }

        if (\count($success)) {
            $this->addStatusMessage(sprintf(
                _('Bylo vygenerováno %s ostatních závazků z %s zálohových faktur'),
                \count($success),
                \count($this->zalohy),
            ));
        }

        return $success;
    }

    /**
     * Smazat zálohové faktury ze kterých byly úspěšně vytvořeny ostatní pohledávky.
     */
    public function uklidZpracovaneZalohy(): void
    {
        $this->setEvidence('faktura-prijata');

        if (!empty($this->zalohy)) {
            foreach ($this->zalohy as $id => $zaloha) {
                if (!$this->deleteFromAbraFlexi((int) $zaloha['id'])) {
                    $this->addStatusMessage(sprintf(
                        _('Nepodařilo se smazat zálohovou fakturu %s'),
                        $id,
                    ), 'warning');
                }
            }

            $this->addStatusMessage(
                _('Zálohové faktury převedené na závazky byly smazány'),
                'success',
            );
        }
    }

    /**
     * Převede zálohu na závazek.
     *
     * @param array $zaloha
     */
    public function convert($zaloha)
    {
        $engine = new \AbraFlexi\Bricks\Convertor($zaloha, new \AbraFlexi\Zavazek(['typDokl' => \AbraFlexi\Code::ensure(\Ease\Shared::cfg('LIABILITY_DOCUMENT_TYPE'))]));

        return $engine->conversion();
    }
}
