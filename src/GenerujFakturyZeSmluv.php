<?php

namespace AbraFlexi;

use Ease\Shared;

/**
 * Generate invoices from Contracts
 *
 * @author     Vítězslav Dvořák <vitex@arachne.cz>
 * @copyright  2018-2022 Spoje.Net
 */
define('EASE_APPNAME', 'Contracts2Invoices');
require_once '../vendor/autoload.php';
if (file_exists('../.env')) {
    (new Shared())->loadConfig('../.env', true);
}
$cfgKeys = ['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY'];
$configured = true;
foreach ($cfgKeys as $cfgKey) {
    if (empty(\Ease\Functions::cfg($cfgKey))) {
        fwrite(STDERR, 'Requied configuration '.$cfgKey." is not set.".PHP_EOL);
        $configured = false;
    }
}
if ($configured === false) {
    exit(1);
}

$invoicer = new FakturaVydana();
$contractor = new Smlouva();
$contractor->logBanner(\Ease\Shared::appName());
$contractList = $contractor->getColumnsFromAbraFlexi(['id', 'kod', 'nazev', 'firma'],
    ['autoGen' => true, 'limit' => 0]);
if ($contractList) {
    foreach ($contractList as $counter => $contractInfo) {
        $message = $counter.'/'.count($contractList).' '.$contractInfo['nazev'].' '.$contractInfo['firma']->showAs;

        $contractor->setMyKey($contractInfo['id']);

        if ($contractor->generovaniFaktur()) {
            if (array_key_exists('messages', $contractor->lastResult)) {
                if (strstr($contractor->lastResult['messages']['message'],
                        'Nebyla')) {
                    $contractor->addStatusMessage($message, 'debug');
                } else {

                    if (strstr($contractor->lastResult['messages']['message'],
                            'faktur') && strstr($contractor->lastResult['messages']['message'],
                            ':')) {
                        $hmr = explode(':',
                            $contractor->lastResult['messages']['message']);
                        $howMany = intval(end($hmr));
                        $generated = $invoicer->getColumnsFromAbraFlexi(['kod'],
                            [
                                'firma' => \AbraFlexi\RO::code($contractInfo['firma']),
                                'cisSml' => $contractInfo['kod'],
                                'limit' => $howMany
                        ]);

                        $invoices = [];
                        foreach ($generated as $result) {
                            $invoices[] = $result['kod'];
                        }

                        $contractor->addStatusMessage($message.' '.implode(',',
                                $invoices), 'success');
                    }
                }
            } else {
                if (array_key_exists('success', $contractor->lastResult) && ($contractor->lastResult['success']
                    == 'failed')) {
                    $contractor->addStatusMessage($message, 'warning');
                }
            }
        } else {
            $status = $contractor->lastResponseCode == 500 ? 'error' : 'warning';
            $notice = str_replace('"', ' ',
                trim(json_encode($contractor->getErrors()), '[{}]'));
            $contractor->addStatusMessage($message.' '.$notice, $status);
        }
    }
}
