<?php

use Ease\Shared;

/**
 * Generate invoices from Contracts
 *
 * @author     Vítězslav Dvořák <vitex@vitexsoftware.com>
 * @copyright  2018-2024 Spoje.Net
 */

define('EASE_APPNAME', 'AbraFlexi Contracts2Invoices');
require_once '../vendor/autoload.php';

Shared::init(['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY'], '../.env');

$invoicer = new \AbraFlexi\FakturaVydana();
$contractor = new \AbraFlexi\Smlouva();
$contractTypor = new \AbraFlexi\RO(null, ['evidence' => 'typ-smlouvy']);
if (\Ease\Shared::cfg('APP_DEBUG', false)) {
    $contractor->logBanner();
}

$contractTypeList = $contractTypor->getColumnsFromAbraFlexi(
    ['kod'],
    ['autoGen' => true, 'limit' => 0],
    'kod'
);

$quote = function (string $value) {
    return "'$value'";
};

$contractTypeCond = empty($contractTypeList) ? 'autoGen eq true' : '(typSml in (' . implode(',', array_map($quote, array_map('\AbraFlexi\Functions::code', array_keys($contractTypeList)))) . ') OR autoGen eq true)';

$contractList = $contractor->getColumnsFromAbraFlexi(
    ['id', 'kod', 'nazev', 'firma'],
    [$contractTypeCond, 'limit' => 0]
);
if ($contractList) {
    foreach ($contractList as $counter => $contractInfo) {
        $message = ($counter + 1) . '/' . count($contractList) . ' ' . $contractInfo['nazev'] . ' ' . $contractInfo['firma']->showAs;

        $contractor->setMyKey($contractInfo['id']);

        if ($contractor->generovaniFaktur()) {
            if (array_key_exists('messages', $contractor->lastResult)) {
                if (
                    strstr(
                        $contractor->lastResult['messages']['message'],
                        'Nebyla'
                    )
                ) {
                    $contractor->addStatusMessage($message . ': ' . $contractor->lastResult['messages']['message'], 'debug');
                } else {
                    if (
                        strstr(
                            $contractor->lastResult['messages']['message'],
                            'faktur'
                        ) && strstr(
                            $contractor->lastResult['messages']['message'],
                            ':'
                        )
                    ) {
                        $hmr = explode(
                            ':',
                            $contractor->lastResult['messages']['message']
                        );
                        $howMany = intval(end($hmr));
                        $generated = $invoicer->getColumnsFromAbraFlexi(
                            ['kod'],
                            [
                                    'firma' => \AbraFlexi\Functions::code($contractInfo['firma']),
                                    'cisSml' => $contractInfo['kod'],
                                    'limit' => $howMany
                                ]
                        );

                        $invoices = [];
                        foreach ($generated as $result) {
                            $invoices[] = $result['kod'];
                        }

                        $contractor->addStatusMessage($message . ' ' . implode(
                            ',',
                            $invoices
                        ), 'success');
                    }
                }
            } else {
                if (array_key_exists('success', $contractor->lastResult) && ($contractor->lastResult['success'] == 'failed')) {
                    $contractor->addStatusMessage($message, 'warning');
                }
            }
        } else {
            $status = $contractor->lastResponseCode == 500 ? 'error' : 'warning';
            $notice = str_replace(
                '"',
                ' ',
                trim(json_encode($contractor->getErrors()), '[{}]')
            );
            $contractor->addStatusMessage($message . ' ' . $notice, $status);
        }
    }
} else {
    $contractor->addStatusMessage(_('No Contract with AutoGenerate flag found'), 'debug');
}
