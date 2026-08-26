<?php
/**
 * CLI checks for PnPApi and PnPLogger (no WHMCS runtime).
 *
 * Run: php WHMCS_v9.0.x/tests/test_api_protocol.php
 */

declare(strict_types=1);

$base = dirname(__DIR__) . '/src/api/modules/gateways/plugnpayapi';
require_once $base . '/PnPApi.php';
require_once $base . '/PnPLogger.php';

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

assert_true(PnPApi::formatCardExp('0130') === '01/30', 'MMYY becomes MM/YY');
assert_true(PnPApi::formatCardExp('01/30') === '01/30', 'MM/YY is kept');
assert_true(PnPApi::formatCardExp('01-30') === '01/30', 'MM-YY digits become MM/YY');

$fields = PnPApi::buildAuthorizeFields([
    'authtype' => 'authonly',
    'amount' => '12.34',
    'currency' => 'USD',
    'invoice_id' => '42',
    'card_number' => '4111-1111-1111-1111',
    'card_exp' => '0130',
    'card_name' => 'Jane Doe',
    'card_cvv' => '123',
    'card_company' => 'Acme',
    'card_address1' => '1 Main St',
    'card_city' => 'Portland',
    'card_state' => 'OR',
    'card_zip' => '97201',
    'card_country' => 'US',
    'phone' => '555-0100',
    'email' => 'jane@example.com',
    'ship_name' => 'Jane Doe',
    'ship_address1' => '1 Main St',
    'ship_city' => 'Portland',
    'ship_state' => 'OR',
    'ship_zip' => '97201',
    'ship_country' => 'US',
    'ip_address' => '203.0.113.10',
    'items' => [
        ['sku' => 'SKU-1', 'price' => 6.17, 'quantity' => 2, 'name' => 'Widget'],
    ],
]);

assert_true($fields['mode'] === 'auth', 'mode auth');
assert_true($fields['paymethod'] === 'credit', 'paymethod credit');
assert_true($fields['authtype'] === 'authonly', 'authtype authonly');
assert_true($fields['easycart'] === '1', 'easycart');
assert_true($fields['orderID'] === '42', 'orderID is invoice id');
assert_true($fields['acct_code'] === '42', 'acct_code is invoice id');
assert_true($fields['card-amount'] === '12.34', 'card-amount');
assert_true($fields['card-number'] === '4111111111111111', 'card-number digits only');
assert_true($fields['card-exp'] === '01/30', 'card-exp MM/YY from WHMCS MMYY');
assert_true($fields['card-name'] === 'Jane Doe', 'card-name');
assert_true($fields['card-cvv'] === '123', 'card-cvv present when provided');
assert_true($fields['currency'] === 'USD', 'currency');
assert_true($fields['dontsndmail'] === 'yes', 'dontsndmail yes');
assert_true($fields['item1'] === 'SKU-1', 'line item sku');
assert_true($fields['cost1'] === '6.17', 'line item cost');
assert_true($fields['quantity1'] === '2', 'line item qty');
assert_true(PnPApi::ENDPOINT === 'https://pay1.plugnpay.com/payment/pnpremote.cgi', 'endpoint');
assert_true(PnPApi::CLIENT_IDENTIFIER === 'WHMCS_API', 'client identifier');
assert_true(PnPApi::VERSION === '1.0.1', 'protocol version');

$sale = PnPApi::buildAuthorizeFields([
    'authtype' => 'authpostauth',
    'amount' => '1.00',
    'card_number' => '4111111111111111',
    'card_exp' => '0130',
    'card_name' => 'Jane Doe',
    'invoice_id' => '9',
]);
assert_true($sale['authtype'] === 'authpostauth', 'authtype sale');
assert_true(!isset($sale['card-cvv']), 'omit empty cvv');

$zero = PnPApi::buildAuthorizeFields([
    'authtype' => 'authonly',
    'amount' => '0.00',
    'card_number' => '4111111111111111',
    'card_exp' => '0130',
    'card_name' => 'Jane Doe',
    'invoice_id' => '9',
]);
assert_true($zero['mode'] === 'checkcard', 'zero amount uses checkcard');
assert_true(!isset($zero['card-amount']), 'zero amount omits card-amount');

$parsed = PnPApi::parseResponse(
    'FinalStatus=success&success=yes&orderID=PNP-99&auth-code=TST123&avs-code=Y&cvvresp=M'
);
$api = new PnPApi('demoacct', 'not-a-real-password');
assert_true($api->isApproved($parsed), 'success FinalStatus is approved');
assert_true(PnPApi::transactionId($parsed) === 'PNP-99', 'transaction id from orderID');
assert_true(!$api->isApproved(['FinalStatus' => 'badcard', 'success' => 'no']), 'decline is not approved');
assert_true(PnPApi::declineReason(['MErrMsg' => 'Insufficient funds']) === 'Insufficient funds', 'decline reason from MErrMsg');
assert_true(PnPApi::declineReason(['FinalStatus' => 'fraud']) === 'fraud', 'decline reason falls back to status');

$logger = new PnPLogger(sys_get_temp_dir(), false);
$sanitized = $logger->sanitize([
    'card-number' => '4111111111111111',
    'card-cvv' => '123',
    'publisher-password' => 'secret',
    'cardnum' => '4111111111111111',
    'cccvv' => '999',
    'publisher-name' => 'pnpdemo',
    'fields' => [
        'card-number' => '4111111111111111',
        'publisher-password' => 'secret',
    ],
]);
assert_true($sanitized['card-number'] === 'XXXXXXXXXXXX1111', 'PAN last four only');
assert_true($sanitized['card-cvv'] === '***REDACTED***', 'CVV redacted');
assert_true($sanitized['publisher-password'] === '***REDACTED***', 'password redacted');
assert_true($sanitized['cardnum'] === 'XXXXXXXXXXXX1111', 'WHMCS cardnum last four only');
assert_true($sanitized['cccvv'] === '***REDACTED***', 'WHMCS cccvv redacted');
assert_true($sanitized['publisher-name'] === 'pnpdemo', 'account not redacted');
assert_true($sanitized['fields']['card-number'] === 'XXXXXXXXXXXX1111', 'nested PAN redacted');
assert_true(strpos(print_r($sanitized, true), '4111111111111111') === false, 'sanitized dump has no full PAN');
assert_true(strpos(print_r($sanitized, true), 'secret') === false, 'sanitized dump has no password');

$gateway = dirname(__DIR__) . '/src/api/modules/gateways/plugnpayapi.php';
$gatewaySrc = is_file($gateway) ? (string)file_get_contents($gateway) : '';
assert_true($gatewaySrc !== '', 'gateway file is present');
assert_true(str_contains($gatewaySrc, 'function plugnpayapi_capture'), 'gateway defines _capture');
assert_true(!str_contains($gatewaySrc, 'function plugnpayapi_link'), 'gateway has no _link');
assert_true(!str_contains($gatewaySrc, 'function plugnpayapi_refund'), 'gateway does not stub refund');
assert_true(str_contains($gatewaySrc, "'DisableLocalCreditCardInput' => false"), 'WHMCS collects card fields');
assert_true(str_contains($gatewaySrc, 'PnPApi::buildAuthorizeFields'), 'gateway uses protocol helper');
assert_true(str_contains($gatewaySrc, 'https://'), 'gateway requires HTTPS systemurl');
assert_true(str_contains($gatewaySrc, 'sanitize'), 'gateway sanitizes rawdata');
assert_true(str_contains($gatewaySrc, 'WHMCS-'), 'gateway falls back when orderID is missing');
assert_true(str_contains($gatewaySrc, 'number_format'), 'gateway formats amount to two decimals');
assert_true(!str_contains($gatewaySrc, 'tblinvoiceitems'), 'gateway does not send invoice line items');

if ($failed > 0) {
    echo "\n{$failed} check(s) failed\n";
    exit(1);
}

echo "\nAll checks passed\n";
exit(0);
