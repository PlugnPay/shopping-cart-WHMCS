<?php
/**
 * CLI checks for PnPSs2Protocol and PnPSs2Logger (no WHMCS runtime).
 *
 * Run: php WHMCS_v9.0.x/tests/test_ss2_protocol.php
 */

declare(strict_types=1);

$base = dirname(__DIR__) . '/src/ss2/modules/gateways/plugnpayss2';
require_once $base . '/PnPSs2Protocol.php';
require_once $base . '/PnPSs2Logger.php';

$failed = 0;

function assert_true(bool $cond, string $label): void
{
    global $failed;
    if ($cond) {
        echo "OK  {$label}\n";
        return;
    }
    $failed++;
    echo "FAIL {$label}\n";
}

$fields = PnPSs2Protocol::buildHostedFields([
    'gateway_account' => 'pnpdemo',
    'amount' => '12.34',
    'currency' => 'USD',
    'invoice_id' => '42',
    'success_url' => 'https://billing.example/modules/gateways/callback/plugnpayss2.php',
    'billing_name' => 'Jane Doe',
    'billing_email' => 'jane@example.com',
    'ip_address' => '203.0.113.10',
]);

assert_true($fields['pt_gateway_account'] === 'pnpdemo', 'gateway account mapped');
assert_true($fields['pt_transaction_amount'] === '12.34', 'amount mapped');
assert_true($fields['pb_post_auth'] === 'no', 'authorization-only');
assert_true($fields['pb_transition_type'] === 'post', 'transition type post');
assert_true($fields['pd_display_items'] === 'no', 'no line items');
assert_true($fields['pt_client_identifier'] === 'WHMCS_SS2', 'client identifier');
assert_true($fields['pt_custom_name_1'] === PnPSs2Protocol::CUSTOM_INVOICE, 'custom invoice name');
assert_true($fields['pt_custom_value_1'] === '42', 'custom invoice value');
assert_true($fields['pt_custom_name_2'] === PnPSs2Protocol::CUSTOM_AMOUNT, 'custom amount name');
assert_true($fields['pt_custom_value_2'] === '12.34', 'custom amount value');
assert_true($fields['pt_account_code_1'] === '42', 'account code 1 is invoice id');
assert_true($fields['pb_success_url'] === 'https://billing.example/modules/gateways/callback/plugnpayss2.php', 'success url');
assert_true($fields['pt_payment_name'] === 'Jane Doe', 'payment name mapped');
assert_true(PnPSs2Protocol::ENDPOINT === 'https://pay1.plugnpay.com/pay/', 'hosted endpoint');
assert_true(PnPSs2Protocol::VERSION === '1.0.1', 'protocol version');

$success = [
    'pi_response_status' => 'success',
    'pt_transaction_amount' => '12.34',
    'pt_order_id' => 'ord-99',
    'pt_gateway_account' => 'pnpdemo',
    'pt_custom_name_1' => 'whmcsinvoice',
    'pt_custom_value_1' => '42',
    'pt_custom_name_2' => 'whmcsamount',
    'pt_custom_value_2' => '12.34',
];
assert_true(PnPSs2Protocol::isSuccessfulResponse($success), 'success status');
assert_true(!PnPSs2Protocol::isSuccessfulResponse(['pi_response_status' => 'badcard']), 'decline is not success');
assert_true(!PnPSs2Protocol::isSuccessfulResponse(['pi_response_status' => '']), 'empty status is not success');
assert_true(PnPSs2Protocol::amountsMatch('12.34', '12.340'), 'amount match ignores extra zeros');
assert_true(!PnPSs2Protocol::amountsMatch('12.34', '12.35'), 'amount mismatch');
assert_true(PnPSs2Protocol::extractCustomValue($success, 'whmcsinvoice') === '42', 'extract invoice custom');
assert_true(PnPSs2Protocol::extractCustomValue($success, 'whmcsamount') === '12.34', 'extract amount custom');
assert_true(PnPSs2Protocol::extractCustomValue(['whmcsinvoice' => 'flat'], 'whmcsinvoice') === 'flat', 'flat custom fallback');
assert_true(PnPSs2Protocol::callbackInvoiceId($success) === '42', 'callback invoice from custom');
assert_true(PnPSs2Protocol::callbackInvoiceId(['pt_account_code_1' => '7']) === '7', 'callback invoice from account code');
assert_true(PnPSs2Protocol::callbackTransactionId($success) === 'ord-99', 'callback transaction id');
assert_true(PnPSs2Protocol::callbackAmount($success) === '12.34', 'callback amount');
assert_true(PnPSs2Protocol::callbackFee([]) === '0', 'empty fee defaults to 0');
assert_true(PnPSs2Protocol::gatewayAccountMatches($success, 'pnpdemo'), 'gateway account match');
assert_true(PnPSs2Protocol::gatewayAccountMatches($success, 'PNPDEMO'), 'gateway account match is case-insensitive');
assert_true(!PnPSs2Protocol::gatewayAccountMatches($success, 'other'), 'gateway account mismatch');
assert_true(PnPSs2Protocol::gatewayAccountMatches(['pi_response_status' => 'success'], 'pnpdemo'), 'missing returned account is allowed');
assert_true(
    PnPSs2Protocol::callbackUrl('https://billing.example/', 'plugnpayss2')
        === 'https://billing.example/modules/gateways/callback/plugnpayss2.php',
    'callback url trims slash'
);
assert_true(
    PnPSs2Protocol::invoiceViewUrl('https://billing.example', 42)
        === 'https://billing.example/viewinvoice.php?id=42',
    'invoice view url'
);

assert_true(PnPSs2Protocol::invoiceAmountDue('100.00', '0', '0', '0') === '100.00', 'full invoice due');
assert_true(PnPSs2Protocol::invoiceAmountDue('100.00', '10', '25', '0') === '65.00', 'due after credit and payment');
assert_true(PnPSs2Protocol::invoiceAmountDue('100.00', '0', '40', '10') === '70.00', 'due after refunded payment');
assert_true(PnPSs2Protocol::invoiceAmountDue('10.00', '0', '20', '0') === '0.00', 'due does not go negative');

$logger = new PnPSs2Logger(sys_get_temp_dir(), false);
$sanitized = $logger->sanitize([
    'pt_card_number' => '4111111111111111',
    'card_cvv' => '123',
    'publisher-password' => 'secret',
    'pt_gateway_account' => 'pnpdemo',
]);
assert_true($sanitized['pt_card_number'] === 'XXXXXXXXXXXX1111', 'PAN last four only');
assert_true($sanitized['card_cvv'] === '***REDACTED***', 'CVV redacted');
assert_true($sanitized['publisher-password'] === '***REDACTED***', 'password redacted');
assert_true($sanitized['pt_gateway_account'] === 'pnpdemo', 'account not redacted');
assert_true($logger->isEnabled() === false, 'logger disabled by default in this check');

$gateway = dirname(__DIR__) . '/src/ss2/modules/gateways/plugnpayss2.php';
$callback = dirname(__DIR__) . '/src/ss2/modules/gateways/callback/plugnpayss2.php';
$gatewaySrc = is_file($gateway) ? (string)file_get_contents($gateway) : '';
$callbackSrc = is_file($callback) ? (string)file_get_contents($callback) : '';

assert_true($gatewaySrc !== '', 'gateway file is present');
assert_true($callbackSrc !== '', 'callback file is present');
assert_true(str_contains($gatewaySrc, 'function plugnpayss2_link'), 'gateway defines _link');
assert_true(str_contains($gatewaySrc, 'not configured'), 'gateway refuses empty account');
assert_true(str_contains($gatewaySrc, 'PnPSs2Protocol::buildHostedFields'), 'gateway uses protocol helper');
assert_true(str_contains($gatewaySrc, 'htmlspecialchars'), 'gateway HTML-escapes form fields');
assert_true(!str_contains($gatewaySrc, 'function plugnpayss2_refund'), 'gateway does not stub refund');
assert_true(!str_contains($gatewaySrc, 'function plugnpayss2_cancelSubscription'), 'gateway does not stub cancel');
assert_true(!preg_match('/\$username\b/', $gatewaySrc), 'gateway has no undefined $username');
assert_true(str_contains($callbackSrc, 'plugnpayss2_invoiceAmountDue'), 'callback compares invoice balance');
assert_true(str_contains($callbackSrc, 'Already Recorded'), 'callback redirects duplicate transactions');
assert_true(str_contains($callbackSrc, 'Return to invoice'), 'callback has HTML return fallback');
assert_true(str_contains($callbackSrc, 'PnPSs2Protocol::isSuccessfulResponse'), 'callback checks success status');
assert_true(str_contains($callbackSrc, 'addInvoicePayment'), 'callback records invoice payment');
assert_true(str_contains($callbackSrc, 'invoiceViewUrl'), 'callback redirects to invoice');
assert_true(str_contains($callbackSrc, 'Missing Transaction ID'), 'callback requires transaction id');
assert_true(!str_contains($callbackSrc, 'REMOTE_ADDR'), 'callback does not use IP allowlist');

if ($failed > 0) {
    echo "\n{$failed} check(s) failed\n";
    exit(1);
}

echo "\nAll checks passed\n";
exit(0);
