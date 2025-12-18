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
\define('EASE_APPNAME', 'AbraFlexi Contracts2Receivables');

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
$contractTypor = new \AbraFlexi\RO(null, ['evidence' => 'typ-smlouvy']);

if (strtolower(Shared::cfg('APP_DEBUG', '')) === 'true') {
    $contractor->logBanner(\Ease\Shared::appName().' v'.\Ease\Shared::appVersion());
}


$worker = new Poh
// $worker->pripravSmlouvy();
$worker->nactiZalohoveFaktury();
$worker->zkonvertujZalohyNaZavazky();
$worker->ulozZavazky();
$worker->uklidZpracovaneZalohy();
