<?php
/**
 * PlugnPay Smart Screens v2 callback for WHMCS 9.0.x
 *
 * Receives the browser POST from https://pay1.plugnpay.com/pay/ and records
 * the invoice payment on success.
 *
 * @see https://developers.whmcs.com/payment-gateways/callbacks/
 *
 * @copyright Copyright (c) PlugnPay Technologies
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0
 */

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';
require_once __DIR__ . '/../plugnpayss2/PnPSs2Protocol.php';
require_once __DIR__ . '/../plugnpayss2/PnPSs2Logger.php';

/**
 * Remaining balance for an invoice, or empty string if it cannot be loaded.
 *
 * @param mixed $invoiceId
 */
function plugnpayss2_invoiceAmountDue($invoiceId): string
{
    if (!class_exists('\WHMCS\Database\Capsule')) {
        return '';
    }

    $invoice = \WHMCS\Database\Capsule::table('tblinvoices')->where('id', $invoiceId)->first();
    if (!$invoice) {
        return '';
    }

    $paid = (float) \WHMCS\Database\Capsule::table('tblaccounts')
        ->where('invoiceid', $invoiceId)
        ->sum('amountin');
    $refunded = (float) \WHMCS\Database\Capsule::table('tblaccounts')
        ->where('invoiceid', $invoiceId)
        ->sum('amountout');

    return PnPSs2Protocol::invoiceAmountDue(
        $invoice->total,
        $invoice->credit ?? 0,
        $paid,
        $refunded
    );
}

function plugnpayss2_transactionExists(string $transactionId): bool
{
    if ($transactionId === '' || !class_exists('\WHMCS\Database\Capsule')) {
        return false;
    }

    return \WHMCS\Database\Capsule::table('tblaccounts')->where('transid', $transactionId)->exists();
}

/**
 * @param mixed $invoiceId
 */
function plugnpayss2_redirectToInvoice(string $systemUrl, $invoiceId): void
{
    $url = PnPSs2Protocol::invoiceViewUrl($systemUrl, $invoiceId !== '' && $invoiceId !== null ? $invoiceId : '0');
    if (!headers_sent()) {
        header('Location: ' . $url);
    }
    echo '<p><a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '">Return to invoice</a></p>';
    exit;
}

$gatewayModuleName = basename(__FILE__, '.php');
$gatewayParams = getGatewayVariables($gatewayModuleName);

if (!$gatewayParams['type']) {
    die('Module Not Activated');
}

$logger = new PnPSs2Logger(
    dirname(__DIR__) . '/plugnpayss2/logs',
    !empty($gatewayParams['debugLog'])
);
$logger->log('Response-Data', $_POST);

$invoiceId = PnPSs2Protocol::callbackInvoiceId($_POST);
$transactionId = PnPSs2Protocol::callbackTransactionId($_POST);
$paymentAmount = PnPSs2Protocol::callbackAmount($_POST);
$paymentFee = PnPSs2Protocol::callbackFee($_POST);
$expectedAmount = PnPSs2Protocol::extractCustomValue($_POST, PnPSs2Protocol::CUSTOM_AMOUNT);
$accountId = trim((string)($gatewayParams['accountId'] ?? ''));
$systemUrl = (string)($gatewayParams['systemurl'] ?? '');

$success = PnPSs2Protocol::isSuccessfulResponse($_POST);
$transactionStatus = $success ? 'Success' : 'Failure';

if ($invoiceId === '') {
    $transactionStatus = 'Missing Invoice ID';
    $success = false;
}

if ($success && $transactionId === '') {
    $transactionStatus = 'Missing Transaction ID';
    $success = false;
}

if ($success && !PnPSs2Protocol::gatewayAccountMatches($_POST, $accountId)) {
    $transactionStatus = 'Gateway Account Mismatch';
    $success = false;
}

if ($invoiceId !== '') {
    $invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['name']);
}

$due = ($invoiceId !== '') ? plugnpayss2_invoiceAmountDue($invoiceId) : '';
if ($success && $due !== '' && !PnPSs2Protocol::amountsMatch($paymentAmount, $due)) {
    $transactionStatus = 'Amount Mismatch';
    $success = false;
} elseif ($success && $due === '' && $expectedAmount !== '' && !PnPSs2Protocol::amountsMatch($paymentAmount, $expectedAmount)) {
    $transactionStatus = 'Amount Mismatch';
    $success = false;
}

if ($transactionId !== '' && plugnpayss2_transactionExists($transactionId)) {
    $logger->log('Already recorded', ['transid' => $transactionId, 'invoiceid' => $invoiceId]);
    logTransaction($gatewayParams['name'], $logger->sanitize($_POST), 'Already Recorded');
    plugnpayss2_redirectToInvoice($systemUrl, $invoiceId);
}

if ($success && $transactionId !== '') {
    checkCbTransID($transactionId);
}

logTransaction($gatewayParams['name'], $logger->sanitize($_POST), $transactionStatus);

if ($success) {
    addInvoicePayment(
        $invoiceId,
        $transactionId,
        $paymentAmount,
        $paymentFee,
        $gatewayModuleName
    );
}

plugnpayss2_redirectToInvoice($systemUrl, $invoiceId);
