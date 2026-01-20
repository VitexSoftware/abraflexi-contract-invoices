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

/**
 * Generate invoices from Contracts.
 *
 * @author     Vítězslav Dvořák <vitex@vitexsoftware.com>
 * @copyright  2018-2025 Spoje.Net
 */

use AbraFlexi\Contracts\ZalohyZeSmluvDoZavazku;
use Ease\Shared;

\define('EASE_APPNAME', 'AbraFlexi Contracts2Liabilities');

require_once '../vendor/autoload.php';

$options = getopt('o::e::', ['output::', 'environment::']);
$jsonOutput = [];

Shared::init(
    ['ABRAFLEXI_URL', 'ABRAFLEXI_LOGIN', 'ABRAFLEXI_PASSWORD', 'ABRAFLEXI_COMPANY', 'LIABILITY_INVOICE_TYPE', 'LIABILITY_DOCUMENT_TYPE'],
    \array_key_exists('environment', $options) ? $options['environment'] : (\array_key_exists('e', $options) ? $options['e'] : '../.env'),
);

$destination = \array_key_exists('output', $options) ? $options['output'] : Shared::cfg('RESULT_FILE', 'php://stdout');

$contractor = new ZalohyZeSmluvDoZavazku();

if (strtolower(Shared::cfg('APP_DEBUG', '')) === 'true') {
    $contractor->logBanner(Shared::appName().' v'.Shared::appVersion());
}

$contractor->nactiZalohoveFaktury();
$contractor->zkonvertujZalohyNaZavazky();
$contractor->ulozZavazky();
$contractor->uklidZpracovaneZalohy();

$jsonOutput = $contractor->report();

$written = file_put_contents($destination, json_encode($jsonOutput, \Ease\Shared::cfg('DEBUG') ? \JSON_PRETTY_PRINT : 0));
$contractor->addStatusMessage(sprintf(_('Saving result to %s'), $destination), $written ? 'success' : 'error');

exit($written ? 0 : 1);
