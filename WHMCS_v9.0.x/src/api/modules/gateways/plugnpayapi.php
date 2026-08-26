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
    $accountId = PnPApi::filterAccount($params['accountId'] ?? '');
    $remotePassword = PnPApi::filterPassword($params['remotePassword'] ?? '');
    $authType = ((string)($params['authType'] ?? 'authonly') === 'authpostauth')
        ? 'authpostauth'
        : 'authonly';
    $systemUrl = PnPApi::filterHttpsUrl($params['systemurl'] ?? '');
    $invoiceId = PnPApi::filterInvoiceId($params['invoiceid'] ?? '');
    $amount = PnPApi::filterAmount($params['amount'] ?? 0);
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

    if ($systemUrl === '') {
        return [
            'status' => 'error',
            'declinereason' => 'Storefront HTTPS is required for Remote API card collection.',
            'rawdata' => 'systemurl is not HTTPS',
        ];
    }

    $cardNumber = PnPApi::filterCardNumber($params['cardnum'] ?? '');
    $cardExp = PnPApi::formatCardExp((string)($params['cardexp'] ?? ''));
    if ($cardNumber === '' || $cardExp === '') {
        return [
            'status' => 'declined',
            'declinereason' => 'A valid card number and expiry are required.',
            'rawdata' => 'Invalid cardnum or cardexp',
        ];
    }

    $billingName = trim(
        PnPApi::filterString($client['firstname'] ?? '', 40) . ' ' . PnPApi::filterString($client['lastname'] ?? '', 40)
    );

    $fields = PnPApi::buildAuthorizeFields([
        'authtype' => $authType,
        'amount' => $amount,
        'currency' => $params['currency'] ?? '',
        'invoice_id' => $invoiceId,
        'card_number' => $cardNumber,
        'card_exp' => $cardExp,
        'card_name' => $billingName,
        'card_cvv' => $params['cccvv'] ?? '',
        'card_company' => $client['companyname'] ?? '',
        'card_address1' => $client['address1'] ?? '',
        'card_address2' => $client['address2'] ?? '',
        'card_city' => $client['city'] ?? '',
        'card_state' => $client['state'] ?? '',
        'card_zip' => $client['postcode'] ?? '',
        'card_country' => $client['country'] ?? '',
        'phone' => $client['phonenumber'] ?? '',
        'email' => $client['email'] ?? '',
        'ship_name' => $billingName,
        'ship_address1' => $client['address1'] ?? '',
        'ship_address2' => $client['address2'] ?? '',
        'ship_city' => $client['city'] ?? '',
        'ship_state' => $client['state'] ?? '',
        'ship_zip' => $client['postcode'] ?? '',
        'ship_country' => $client['country'] ?? '',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
    ]);

    $api = new PnPApi($accountId, $remotePassword, $logger);
    $response = $api->authorize($fields);
    unset($fields['card-number'], $fields['card-cvv'], $cardNumber, $params);
    $safe = $logger->sanitize($response);

    if ($api->isApproved($response)) {
        $transid = PnPApi::transactionId($response);
        if ($transid === '') {
            $transid = 'WHMCS-' . $invoiceId;
        }

        return [
            'status' => 'success',
            'transid' => $transid,
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
