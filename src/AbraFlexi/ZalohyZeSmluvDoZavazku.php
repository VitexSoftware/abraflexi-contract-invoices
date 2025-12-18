<?php

/**
 * System.Spoje.Net - Konvertor zálohových faktur do závazků.
 *
 * @author     Vítězslav Dvořák <vitex@arachne.cz>
 * @copyright  2015 Spoje.Net
 */

namespace SpojeNet\System;

/**
 * Description of ZalohyZeSmluvDoZavazku
 *
 * @author vitex
 */
class ZalohyZeSmluvDoZavazku extends \AbraFlexi\RW
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
    public $zavazky = [];

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
        $this->setEvidence('dodavatelska-smlouva');
        $smlouvy = $this->getColumnsFromAbraFlexi(
            'id',
            ['stitky' => 'code:GENERUJ', 'limit' => 0],
            'id'
        );

        if (!empty($smlouvy)) {
            foreach ($smlouvy as $id => $tmp) {
                $generated = $this->performRequest(
                    $id . '/generovani-faktur.xml',
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
            ['typDokl' => 'code:ZAVAZEK', 'limit' => 0, 'relations' => 'polozkyDokladu', 'smlouva' => 'is not empty'],
            'id'
        );

        if (!empty(current($this->zalohy)) == 0) {
            $this->zalohy = [];
        }
    }

    public function zkonvertujZalohyNaZavazky()
    {
        foreach ($this->zalohy as $id => $zaloha) {
            $this->zavazky[$id] = $this->convert(new \AbraFlexi\FakturaPrijata($zaloha));
        }
    }

    public function ulozZavazky()
    {
        $success = [];
        $this->setEvidence('zavazek');

        foreach ($this->zavazky as $id => $zavazek) {
            $this->dataReset();


            $zavazekInserted = $zavazek->insertToAbraFlexi();

            if ($zavazek->lastResponseCode == 201) {
                $zavazekID = (int) $zavazekInserted['results'][0]['id'];
                $zavazek->addStatusMessage(sprintf(
                    _('Závazek %d vytvořen'),
                    $zavazekID
                ));
                $success[$id] = $zavazekID;
            } else {
                unset($this->zalohy[$id]); //Nebyla prevedena nebude se mazat
            }
        }
        if (count($success)) {
            $this->addStatusMessage(sprintf(
                _('Bylo vygenerováno %s ostatních závazků z %s zálohových faktur'),
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
        if (!empty($this->zalohy)) {
            foreach ($this->zalohy as $id => $zaloha) {
                if (!$this->deleteFromAbraFlexi((int) $zaloha['id'])) {
                    $this->addStatusMessage(sprintf(
                        _('Nepodařilo se smazat zálohovou fakturu %s'),
                        $id
                    ), 'warning');
                }
            }
            $this->addStatusMessage(
                _('Zálohové faktury převedené na závazky byly smazány'),
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
        $engine = new \AbraFlexi\Bricks\Convertor($zaloha, new \AbraFlexi\Zavazek(['typDokl' => \AbraFlexi\Functions::code('OST-ZÁVAZKY')]));
        return $engine->conversion();
    }
}
