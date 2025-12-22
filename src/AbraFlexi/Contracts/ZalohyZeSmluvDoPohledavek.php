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
 * Description of ZalohyZeSmluvDoPohledavek.
 *
 * @author vitex
 */
class ZalohyZeSmluvDoPohledavek extends \AbraFlexi\Smlouva
{
    public array $zalohy = [];
    public array $pohledavky = [];

    /**
     * Polozky zalohovych faktur.
     */
    private array $zalohyPolozky = [];

    /**
     * Vygeneruj faktury ze smluv.
     */
    public function pripravSmlouvy(): void
    {
        $this->setEvidence('smlouva');
        $smlouvy = $this->getColumnsFromAbraFlexi(
            'id',
            ['stitky' => 'code:GENERUJ'],
            'id',
        );

        if (\count($smlouvy)) {
            foreach ($smlouvy as $id => $tmp) {
                $generated = $this->performRequest(
                    'smlouva/'.$id.'/generovani-faktur.xml',
                    'PUT',
                    'xml',
                );

                if (isset($generated['messages'])) {
                    if ($generated['success'] === 'ok') {
                        $status = 'success';
                    } else {
                        $status = 'warning';
                    }

                    foreach ($generated['messages'] as $message) {
                        $this->addStatusMessage(
                            $generated['operation'].': '.$message['message'],
                            $status,
                        );
                    }
                }
            }
        }
    }

    /**
     * Načte zálohové doklady.
     */
    public function nactiZalohoveFaktury(): void
    {
        $this->setEvidence('faktura-prijata');
        $this->zalohy = $this->getColumnsFromAbraFlexi(
            '*',
            ['typDokl' => 'code:ZÁLOHA', 'smlouva' => 'is not empty'],
            'id',
        );

        if (\count(current($this->zalohy)) === 0) {
            $this->zalohy = [];
        }

        $this->setEvidence('faktura-prijata-polozka');

        foreach ($this->zalohy as $zid => $zdata) {
            if ($zdata['bezPolozek'] !== 'true') {
                $this->zalohyPolozky[$zid] = $this->getColumnsFromAbraFlexi(
                    '*',
                    ['doklFak' => 'code:'.$zdata['kod']],
                    'id',
                );
            }
        }
    }

    public function zkonvertujZalohyNaPohledavky(): void
    {
        foreach ($this->zalohy as $id => $zaloha) {
            $this->pohledavky[$id] = $this->convert($zaloha);
        }
    }

    public function ulozPohledavky()
    {
        $success = [];
        $this->setEvidence('zavazek');

        foreach ($this->pohledavky as $id => $zavazek) {
            $this->dataReset();
            $this->takeData($zavazek);

            if (\count($this->zalohyPolozky[$id])) {
                $items = [];

                foreach ($this->zalohyPolozky[$id] as $pid => $polozka) {
                    unset($polozka['id'], $polozka['kod'], $polozka['slevaPol'], $polozka['uplSlevaDokl'], $polozka['slevaDokl'], $polozka['cenik'], $polozka['sazbaDphPuv'], $polozka['vyrobniCislaOk'], $polozka['poplatekParentPolFak'], $polozka['zdrojProSkl'], $polozka['zaloha'], $polozka['vyrobniCislaPrijata'], $polozka['vyrobniCislaVydana']);

                    foreach ($polozka as $pkey => $pvalue) {
                        if (strstr($pkey, '@')) {
                            unset($polozka[$pkey]);
                        }

                        if (!$pvalue) {
                            unset($polozka[$pkey]);
                        }
                    }

                    $items[] = $polozka;
                }

                $this->setDataValue('polozkyFaktury', $items);
            }

            $zavazekInserted = $this->insertToAbraFlexi();

            if ($this->lastResponseCode === 201) {
                $zavazekID = (int) $zavazekInserted['results'][0]['id'];
                $this->addStatusMessage(sprintf(
                    _('Pohledávka %d vytvořena'),
                    $zavazekID,
                ));
                $success[$id] = $zavazekID;
            } else {
                unset($this->zalohy[$id]); // Nebyla prevedena nebude se mazat
            }
        }

        if (\count($success)) {
            $this->addStatusMessage(sprintf(
                _('Bylo vygenerováno %s ostatních pohledávek z %s zálohových faktur'),
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

        if (\count($this->zalohy)) {
            foreach ($this->zalohy as $id => $zaloha) {
                if (!$this->deleteFromAbraFlexi((int) $zaloha['id'])) {
                    $this->addStatusMessage(sprintf(
                        _('Nepodařilo se smazat zálohovou fakturu %s'),
                        $id,
                    ), 'warning');
                }
            }

            $this->addStatusMessage(
                _('Zálohové faktury převedené na pohledávky byly smazány'),
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
        $zavazek = [];

        foreach ($zaloha as $zkey => $zvalue) {
            if (!strstr($zkey, '@') && \strlen($zvalue)) {
                $zavazek[$zkey] = $zvalue;
            }
        }

        $zavazek['typDokl'] = 'code:OST-ZÁVAZKY';

        unset($zavazek['id'], $zavazek['slevaDokl'], $zavazek['typDoklBan'], $zavazek['generovatSkl'], $zavazek['hromFakt'], $zavazek['zdrojProSkl'], $zavazek['dobropisovano'], $zavazek['typDoklSkl'], $zavazek['sumOsv'], $zavazek['sumCelkem'], $zavazek['sumZklSniz'], $zavazek['sumZklSniz2'], $zavazek['sumZklZakl'], $zavazek['sumZklCelkem'], $zavazek['sumDphSniz'], $zavazek['sumDphSniz2'], $zavazek['sumDphZakl'], $zavazek['sumDphCelkem'], $zavazek['sumCelkSniz'], $zavazek['sumCelkSniz2'], $zavazek['sumCelkZakl'], $zavazek['sumOsvMen'], $zavazek['sumZklSnizMen'], $zavazek['sumZklSniz2Men'], $zavazek['sumZklZaklMen'], $zavazek['sumZklCelkemMen'], $zavazek['sumDphZaklMen'], $zavazek['sumDphSnizMen'], $zavazek['sumDphSniz2Men'], $zavazek['sumDphCelkemMen'], $zavazek['sumCelkSnizMen'], $zavazek['sumCelkSniz2Men'], $zavazek['sumCelkZaklMen'], $zavazek['sumCelkemMen']);

        $zavazek['datUcto'] = \AbraFlexi\RW::timestampToFlexiDate(time());

        return $zavazek;
    }
}
