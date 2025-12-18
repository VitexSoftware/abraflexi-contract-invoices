<?php

/**
 * System.Spoje.Net - Konvertor zálohových faktur do závazků.
 *
 * @author     Vítězslav Dvořák <vitex@arachne.cz>
 * @copyright  2015 Spoje.Net
 */

namespace SpojeNet\System;

/**
 * Description of ZalohyZeSmluvDoPohledavek
 *
 * @author vitex
 */
class ZalohyZeSmluvDoPohledavek extends \AbraFlexi\RW
{
    /**
     *
     * @var array
     */
    public $zalohy = [];

    /**
     *
     * @var array
     */
    public $pohledavky = [];

    /**
     * Polozky zalohovych faktur
     * @var array
     */
    private $zalohyPolozky = [];

    /**
     * Vygeneruj faktury ze smluv
     */
    public function pripravSmlouvy()
    {
        $this->setEvidence('smlouva');
        $smlouvy = $this->getColumnsFromAbraFlexi(
            'id',
            ['stitky' => 'code:GENERUJ'],
            'id'
        );

        if (count($smlouvy)) {
            foreach ($smlouvy as $id => $tmp) {
                $generated = $this->performRequest(
                    'smlouva/' . $id . '/generovani-faktur.xml',
                    'PUT',
                    'xml'
                );

                if (isset($generated['messages'])) {
                    if ($generated['success'] == 'ok') {
                        $status = 'success';
                    } else {
                        $status = 'warning';
                    }
                    foreach ($generated['messages'] as $message) {
                        $this->addStatusMessage(
                            $generated['operation'] . ': ' . $message['message'],
                            $status
                        );
                    }
                }
            }
        }
    }

    /**
     * Načte zálohové doklady
     */
    public function nactiZalohoveFaktury()
    {
        $this->setEvidence('faktura-prijata');
        $this->zalohy = $this->getColumnsFromAbraFlexi(
            '*',
            ['typDokl' => 'code:ZÁLOHA', 'smlouva' => 'is not empty'],
            'id'
        );

        if (count(current($this->zalohy)) == 0) {
            $this->zalohy = [];
        }

        $this->setEvidence('faktura-prijata-polozka');
        foreach ($this->zalohy as $zid => $zdata) {
            if ($zdata['bezPolozek'] !== 'true') {
                $this->zalohyPolozky[$zid] = $this->getColumnsFromAbraFlexi(
                    '*',
                    ['doklFak' => 'code:' . $zdata['kod']],
                    'id'
                );
            }
        }
    }

    public function zkonvertujZalohyNaPohledavky()
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

            if (count($this->zalohyPolozky[$id])) {
                $items = [];
                foreach ($this->zalohyPolozky[$id] as $pid => $polozka) {
                    unset($polozka['id']);
                    unset($polozka['kod']);
                    unset($polozka['slevaPol']);
                    unset($polozka['uplSlevaDokl']);

                    unset($polozka['slevaDokl']);
                    unset($polozka['cenik']);
                    unset($polozka['sazbaDphPuv']);
                    unset($polozka['vyrobniCislaOk']);
                    unset($polozka['poplatekParentPolFak']);
                    unset($polozka['zdrojProSkl']);
                    unset($polozka['zaloha']);
                    unset($polozka['vyrobniCislaPrijata']);
                    unset($polozka['vyrobniCislaVydana']);

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

            if ($this->lastResponseCode == 201) {
                $zavazekID = (int) $zavazekInserted['results'][0]['id'];
                $this->addStatusMessage(sprintf(
                    _('Pohledávka %d vytvořena'),
                    $zavazekID
                ));
                $success[$id] = $zavazekID;
            } else {
                unset($this->zalohy[$id]); //Nebyla prevedena nebude se mazat
            }
        }
        if (count($success)) {
            $this->addStatusMessage(sprintf(
                _('Bylo vygenerováno %s ostatních pohledávek z %s zálohových faktur'),
                count($success),
                count($this->zalohy)
            ));
        }
        return $success;
    }

    /**
     * Smazat zálohové faktury ze kterých byly úspěšně vytvořeny ostatní pohledávky
     */
    public function uklidZpracovaneZalohy()
    {
        $this->setEvidence('faktura-prijata');
        if (count($this->zalohy)) {
            foreach ($this->zalohy as $id => $zaloha) {
                if (!$this->deleteFromAbraFlexi((int) $zaloha['id'])) {
                    $this->addStatusMessage(sprintf(
                        _('Nepodařilo se smazat zálohovou fakturu %s'),
                        $id
                    ), 'warning');
                }
            }
            $this->addStatusMessage(
                _('Zálohové faktury převedené na pohledávky byly smazány'),
                'success'
            );
        }
    }

    /**
     * Převede zálohu na závazek
     *
     * @param array $zaloha
     */
    function convert($zaloha)
    {
        $zavazek = [];
        foreach ($zaloha as $zkey => $zvalue) {
            if (!strstr($zkey, '@') && strlen($zvalue)) {
                $zavazek[$zkey] = $zvalue;
            }
        }
        $zavazek['typDokl'] = 'code:OST-ZÁVAZKY';

        unset($zavazek['id']);
        unset($zavazek['slevaDokl']);
        unset($zavazek['typDoklBan']);
        unset($zavazek['generovatSkl']);
        unset($zavazek['hromFakt']);
        unset($zavazek['zdrojProSkl']);
        unset($zavazek['dobropisovano']);
        unset($zavazek['typDoklSkl']);
        unset($zavazek['sumOsv']);
        unset($zavazek['sumCelkem']);

        unset($zavazek['sumZklSniz']);
        unset($zavazek['sumZklSniz2']);
        unset($zavazek['sumZklZakl']);
        unset($zavazek['sumZklCelkem']);
        unset($zavazek['sumDphSniz']);
        unset($zavazek['sumDphSniz2']);
        unset($zavazek['sumDphZakl']);
        unset($zavazek['sumDphCelkem']);
        unset($zavazek['sumCelkSniz']);
        unset($zavazek['sumCelkSniz2']);
        unset($zavazek['sumCelkZakl']);
        unset($zavazek['sumOsvMen']);
        unset($zavazek['sumZklSnizMen']);
        unset($zavazek['sumZklSniz2Men']);
        unset($zavazek['sumZklZaklMen']);
        unset($zavazek['sumZklCelkemMen']);
        unset($zavazek['sumDphZaklMen']);
        unset($zavazek['sumDphSnizMen']);
        unset($zavazek['sumDphSniz2Men']);
        unset($zavazek['sumDphCelkemMen']);
        unset($zavazek['sumCelkSnizMen']);
        unset($zavazek['sumCelkSniz2Men']);
        unset($zavazek['sumCelkZaklMen']);
        unset($zavazek['sumCelkemMen']);

        $zavazek['datUcto'] = \AbraFlexi\RW::timestampToFlexiDate(time());
        return $zavazek;
    }
}
