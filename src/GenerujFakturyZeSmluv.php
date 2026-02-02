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

use Ease\Shared;

/**
 * Generate invoices from Contracts.
 *
 * @author     Vítězslav Dvořák <vitex@vitexsoftware.com>
 * @copyright  2018-2025 Spoje.Net
 */
\define('EASE_APPNAME', 'AbraFlexi Contracts2Invoices');

require_once '../vendor/autoload.php';

$options = getopt('o::e::', ['output::', 'environment::']);
$jsonOutput = [];

Shared::init(
    ['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY'],
    \array_key_exists('environment', $options) ? $options['environment'] : (\array_key_exists('e', $options) ? $options['e'] : '../.env'),
);

$destination = \array_key_exists('output', $options) ? $options['output'] : \Ease\Shared::cfg('RESULT_FILE', 'php://stdout');
$invoicer = new \AbraFlexi\FakturaVydana();
$contractor = new \AbraFlexi\Smlouva();
$contractTypor = new \AbraFlexi\TypSmlouvy();

if (strtolower(Shared::cfg('APP_DEBUG', '')) === 'true') {
    $contractor->logBanner(\Ease\Shared::appName().' v'.\Ease\Shared::appVersion());
}

$contractTypeList = $contractTypor->getColumnsFromAbraFlexi(
    ['kod'],
    ['autoGen' => true, 'limit' => 0],
    'kod',
);

$quote = static function (string $value) {
    return "'{$value}'";
};

$contractTypeCond = empty($contractTypeList) ? 'autoGen eq true' : '(typSml in ('.implode(',', array_map($quote, array_map('\AbraFlexi\Functions::code', array_keys($contractTypeList)))).') OR autoGen eq true)';

$contractList = $contractor->getColumnsFromAbraFlexi(
    ['id', 'kod', 'nazev', 'firma'],
    [$contractTypeCond, 'limit' => 0],
);

if ($contractList) {
    foreach ($contractList as $counter => $contractInfo) {
        $contractor->setMyKey($contractInfo['id']);

        $message = ($counter + 1).'/'.\count($contractList).' '.$contractInfo['nazev'].' '.$contractInfo['firma']->showAs;

        $contractor->setObjectName($message);

        if ($contractor->generovaniFaktur()) {
            //  {"winstrom":{"operation":"Generování faktur","success":"ok","results":"","messages":["Nebyla vygenerována žádná nová faktura."],"errors":[],"@version":"1.0"}}
            //  {"winstrom":{"operation":"Generování faktur","success":"failed","results":"","messages":"","errors":[{"message@messageCode":"validace.notNull","message":"IPTV - Pole 'Interní číslo' musí být vyplněno."}],"@version":"1.0"}}

            if ($contractor->getMessages()) {
                if (strstr($contractor->getMessages()[0], 'Nebyla')) {
                    $contractor->addStatusMessage($contractor->getMessages()[0], 'debug');
                } else {
                    if (strstr($contractor->getMessages()[0], 'faktur') && strstr($contractor->getMessages()[0], ':')) {
                        $hmr = explode(':', $contractor->getMessages()[0]);
                        $howMany = (int) end($hmr);
                        $generated = $invoicer->getColumnsFromAbraFlexi(
                            ['kod'],
                            [
                                'firma' => \AbraFlexi\Code::ensure((string) $contractInfo['firma']),
                                'cisSml' => $contractInfo['kod'],
                                'limit' => $howMany,
                            ],
                        );

                        $invoices = [];

                        foreach ($generated as $result) {
                            $invoices[] = $result['kod'];
                        }

                        $contractor->addStatusMessage($message.' '.implode(
                            ',',
                            $invoices,
                        ), 'success');
                    }
                }

                $jsonOutput[$contractInfo['kod']] = $contractInfo['nazev'];
            } else {
                if ($contractor->success() === false) {
                    $jsonOutput[$contractInfo['id']] = $contractor->getErrors();
                    $contractor->addStatusMessage($message, 'warning');
                }
            }
        } else {
            $status = $contractor->lastResponseCode === 500 ? 'error' : 'warning';
            $notice = str_replace(
                '"',
                ' ',
                trim(json_encode($contractor->getErrors()), '[{}]'),
            );
            $contractor->addStatusMessage($message.' '.$notice, $status);
        }
    }
} else {
    $contractor->addStatusMessage(_('No Contract with AutoGenerate flag found'), 'debug');
}

// Create schema-compliant report
$status = 'success';
$message = 'Invoice generation completed';
$metrics = [
    'processed_contracts' => count($contractList ?? []),
    'created_invoices' => count(array_filter($jsonOutput ?? [], function($value) { return !is_array($value); })),
    'failed_contracts' => count(array_filter($jsonOutput ?? [], function($value) { return is_array($value); }))
];

// Check for errors
$hasErrors = false;
$hasWarnings = false;
$statusMessages = $contractor->getStatusMessages();
foreach ($statusMessages as $statusMsg) {
    if ($statusMsg['type'] === 'error') {
        $hasErrors = true;
        break;
    } elseif ($statusMsg['type'] === 'warning') {
        $hasWarnings = true;
    }
}

if ($hasErrors) {
    $status = 'error';
    $message = 'Invoice generation failed';
} elseif ($hasWarnings) {
    $status = 'warning';
    $message = 'Invoice generation completed with warnings';
}

$schemaCompliantOutput = [
    'producer' => 'AbraFlexi Contracts2Invoices',
    'status' => $status,
    'timestamp' => (new \DateTime())->format('c'),
    'message' => $message,
    'artifacts' => [
        'invoices' => $jsonOutput
    ],
    'metrics' => $metrics
];

$written = file_put_contents($destination, json_encode($schemaCompliantOutput, \Ease\Shared::cfg('DEBUG') ? \JSON_PRETTY_PRINT : 0));
$contractor->addStatusMessage(sprintf(_('Saving result to %s'), $destination), $written ? 'success' : 'error');

exit($written ? 0 : 1);
