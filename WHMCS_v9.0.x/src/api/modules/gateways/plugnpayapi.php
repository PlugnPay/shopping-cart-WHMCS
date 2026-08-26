<?php
/**
 * PlugnPay Remote API merchant payment gateway for WHMCS 9.0.x
 *
 * Collects card data in WHMCS and posts from the server to:
 * https://pay1.plugnpay.com/payment/pnpremote.cgi
 *
 * Capture / void / refund are handled in PlugnPay admin, not from WHMCS.
 *
 * @see https://developers.whmcs.com/payment-gateways/merchant-gateway/
 *
 * @copyright Copyright (c) PlugnPay Technologies
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/plugnpayapi/PnPApi.php';
require_once __DIR__ . '/plugnpayapi/PnPLogger.php';

/**
 * @return array<string, mixed>
 */
function plugnpayapi_MetaData()
{
    return [
        'DisplayName' => 'PlugnPay Remote API',
        'APIVersion' => '1.1',
        'DisableLocalCreditCardInput' => false,
        'TokenisedStorage' => false,
    ];
}

/**
 * @return array<string, array<string, mixed>>
 */
function plugnpayapi_config()
{
    return [
        'FriendlyName' => [
            'Type' => 'System',
            'Value' => 'PlugnPay Remote API',
        ],
        'accountId' => [
            'FriendlyName' => 'Gateway Account',
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => 'PlugnPay username (publisher-name)',
        ],
        'remotePassword' => [
            'FriendlyName' => 'Remote Client Password',
            'Type' => 'password',
            'Size' => '25',
            'Default' => '',
            'Description' => 'From PlugnPay Security Administration — not your admin login password',
        ],
        'authType' => [
            'FriendlyName' => 'Authorization Type',
            'Type' => 'dropdown',
            'Options' => [
                'authonly' => 'Authorize only (settle in PlugnPay Admin)',
                'authpostauth' => 'Authorize and settle (sale)',
            ],
            'Default' => 'authonly',
            'Description' => 'WHMCS still marks the invoice paid on success so services can provision',
        ],
        'debugLog' => [
            'FriendlyName' => 'Debug Logging',
            'Type' => 'yesno',
            'Description' => 'Write sanitized logs under modules/gateways/plugnpayapi/logs/',
        ],
    ];
}

/**
 * @param array<string, mixed> $params
 * @return array<string, mixed>
 */
function plugnpayapi_capture($params)
{
    $accountId = trim((string)($params['accountId'] ?? ''));
    $remotePassword = (string)($params['remotePassword'] ?? '');
    $authType = ((string)($params['authType'] ?? 'authonly') === 'authpostauth')
        ? 'authpostauth'
        : 'authonly';
    $systemUrl = (string)($params['systemurl'] ?? '');
    $invoiceId = (string)($params['invoiceid'] ?? '');
    $amount = (string)($params['amount'] ?? '');
    $client = is_array($params['clientdetails'] ?? null) ? $params['clientdetails'] : [];

    $logger = new PnPLogger(
        __DIR__ . '/plugnpayapi/logs',
        !empty($params['debugLog'])
    );

    if ($accountId === '' || $remotePassword === '') {
        return [
            'status' => 'error',
            'declinereason' => 'PlugnPay Remote API is not configured.',
            'rawdata' => 'Missing Gateway Account or Remote Client Password',
        ];
    }

    if (stripos($systemUrl, 'https://') !== 0) {
        return [
            'status' => 'error',
            'declinereason' => 'Storefront HTTPS is required for Remote API card collection.',
            'rawdata' => 'systemurl is not HTTPS',
        ];
    }

    $cardNumber = preg_replace('/\D/', '', (string)($params['cardnum'] ?? '')) ?? '';
    if ($cardNumber === '') {
        return [
            'status' => 'declined',
            'declinereason' => 'Card number is required.',
            'rawdata' => 'Missing cardnum',
        ];
    }

    $billingName = trim((string)($client['firstname'] ?? '') . ' ' . (string)($client['lastname'] ?? ''));
    $items = plugnpayapi_invoiceItems($invoiceId);

    $fields = PnPApi::buildAuthorizeFields([
        'authtype' => $authType,
        'amount' => $amount,
        'currency' => (string)($params['currency'] ?? ''),
        'invoice_id' => $invoiceId,
        'card_number' => $cardNumber,
        'card_exp' => (string)($params['cardexp'] ?? ''),
        'card_name' => $billingName,
        'card_cvv' => (string)($params['cccvv'] ?? ''),
        'card_company' => (string)($client['companyname'] ?? ''),
        'card_address1' => (string)($client['address1'] ?? ''),
        'card_address2' => (string)($client['address2'] ?? ''),
        'card_city' => (string)($client['city'] ?? ''),
        'card_state' => (string)($client['state'] ?? ''),
        'card_zip' => (string)($client['postcode'] ?? ''),
        'card_country' => (string)($client['country'] ?? ''),
        'phone' => (string)($client['phonenumber'] ?? ''),
        'email' => (string)($client['email'] ?? ''),
        'ship_name' => $billingName,
        'ship_address1' => (string)($client['address1'] ?? ''),
        'ship_address2' => (string)($client['address2'] ?? ''),
        'ship_city' => (string)($client['city'] ?? ''),
        'ship_state' => (string)($client['state'] ?? ''),
        'ship_zip' => (string)($client['postcode'] ?? ''),
        'ship_country' => (string)($client['country'] ?? ''),
        'ip_address' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
        'items' => $items,
    ]);

    $api = new PnPApi($accountId, $remotePassword, $logger);
    $response = $api->authorize($fields);
    $safe = $logger->sanitize($response);

    if ($api->isApproved($response)) {
        return [
            'status' => 'success',
            'transid' => PnPApi::transactionId($response),
            'rawdata' => $safe,
        ];
    }

    $reason = PnPApi::declineReason($response);
    $status = (strtolower((string)($response['FinalStatus'] ?? '')) === 'problem')
        ? 'error'
        : 'declined';

    return [
        'status' => $status,
        'declinereason' => $reason,
        'rawdata' => $safe,
    ];
}

/**
 * @param mixed $invoiceId
 * @return list<array<string, mixed>>
 */
function plugnpayapi_invoiceItems($invoiceId): array
{
    if ($invoiceId === '' || !class_exists('\WHMCS\Database\Capsule')) {
        return [];
    }

    $items = [];
    $rows = \WHMCS\Database\Capsule::table('tblinvoiceitems')->where('invoiceid', $invoiceId)->get();
    foreach ($rows as $row) {
        $items[] = [
            'sku' => (string)($row->id ?? ''),
            'name' => (string)($row->description ?? ''),
            'price' => (float)($row->amount ?? 0),
            'quantity' => 1,
        ];
    }

    return $items;
}
