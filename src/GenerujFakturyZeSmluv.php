<?php

namespace AbraFlexi;

use Ease\Shared;

/**
 * Generování faktur
 *
 * @author     Vítězslav Dvořák <vitex@arachne.cz>
 * @copyright  2018-2020 Spoje.Net
 */
define('EASE_APPNAME', 'GenerujFakturyZeSmluv');

require_once '../vendor/autoload.php';
$shared = new Shared();
if (file_exists('../client.json')) {
    $shared->loadConfig('../client.json', true);
}

$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    \Ease\Shared::singleton()->loadConfig($envFile, true);
}

$invoicer = new FakturaVydana();
$contractor = new Smlouva();
$contractor->logBanner(\Ease\Shared::appName());
$contractList = $contractor->getColumnsFromAbraFlexi(['id', 'kod', 'nazev', 'firma'], ['autoGen' => true, 'limit' => 0]);
if ($contractList) {
    foreach ($contractList as $counter => $contractInfo) {
    $message = $counter . '/' . count($contractList) . ' ' . $contractInfo['nazev'] . ' ' . $contractInfo['firma']->showAs;

        $contractor->setMyKey($contractInfo['id']);

        if ($contractor->generovaniFaktur()) {
            if (array_key_exists('messages', $contractor->lastResult)) {
                if (strstr($contractor->lastResult['messages']['message'], 'Nebyla')) {
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

                        $contractor->addStatusMessage($message . ' ' . implode(',',
                                        $invoices), 'success');
                    }
                }
            } else {
                if (array_key_exists('success', $contractor->lastResult) && ($contractor->lastResult['success'] == 'failed')) {
                    $contractor->addStatusMessage($message . ' ' . $notice, 'warning');
                }
            }
        } else {
            $status = $contractor->lastResponseCode == 500 ? 'error' : 'warning';
            $notice = str_replace('"', ' ', trim(json_encode($contractor->getErrors()), '[{}]'));
            $contractor->addStatusMessage($message . ' ' . $notice, $status);
        }
    }
}
