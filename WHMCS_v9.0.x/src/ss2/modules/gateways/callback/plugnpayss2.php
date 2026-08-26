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

if ($success && $expectedAmount !== '' && !PnPSs2Protocol::amountsMatch($paymentAmount, $expectedAmount)) {
    $transactionStatus = 'Amount Mismatch';
    $success = false;
}

if ($success && !PnPSs2Protocol::gatewayAccountMatches($_POST, $accountId)) {
    $transactionStatus = 'Gateway Account Mismatch';
    $success = false;
}

if ($invoiceId !== '') {
    $invoiceId = checkCbInvoiceID($invoiceId, $gatewayParams['name']);
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

$systemUrl = (string)($gatewayParams['systemurl'] ?? '');
header('Location: ' . PnPSs2Protocol::invoiceViewUrl($systemUrl, $invoiceId !== '' ? $invoiceId : '0'));
exit;
