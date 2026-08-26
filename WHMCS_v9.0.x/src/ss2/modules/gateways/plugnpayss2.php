<?php
/**
 * PlugnPay Smart Screens v2 third-party payment gateway for WHMCS 9.0.x
 *
 * Redirects the customer to https://pay1.plugnpay.com/pay/
 *
 * Card data is collected on PlugnPay. Transactions are authorization-only
 * (pb_post_auth=no). Capture / void / refund are handled in PlugnPay admin,
 * not from WHMCS.
 *
 * @see https://developers.whmcs.com/payment-gateways/third-party-gateway/
 *
 * @copyright Copyright (c) PlugnPay Technologies
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0
 */

if (!defined('WHMCS')) {
    die('This file cannot be accessed directly');
}

require_once __DIR__ . '/plugnpayss2/PnPSs2Protocol.php';
require_once __DIR__ . '/plugnpayss2/PnPSs2Logger.php';

/**
 * @return array<string, mixed>
 */
function plugnpayss2_MetaData()
{
    return [
        'DisplayName' => 'PlugnPay Smart Screens v2',
        'APIVersion' => '1.1',
        'DisableLocalCreditCardInput' => true,
        'TokenisedStorage' => false,
    ];
}

/**
 * @return array<string, array<string, mixed>>
 */
function plugnpayss2_config()
{
    return [
        'FriendlyName' => [
            'Type' => 'System',
            'Value' => 'PlugnPay Smart Screens v2',
        ],
        'accountId' => [
            'FriendlyName' => 'Gateway Account',
            'Type' => 'text',
            'Size' => '25',
            'Default' => '',
            'Description' => 'Enter your PlugnPay username here',
        ],
        'debugLog' => [
            'FriendlyName' => 'Debug Logging',
            'Type' => 'yesno',
            'Description' => 'Write sanitized logs under modules/gateways/plugnpayss2/logs/',
        ],
    ];
}

/**
 * HTML form that posts the invoice to PlugnPay hosted Smart Screens.
 *
 * @param array<string, mixed> $params
 */
function plugnpayss2_link($params)
{
    $accountId = trim((string)($params['accountId'] ?? ''));
    $invoiceId = (string)$params['invoiceid'];
    $amount = (string)$params['amount'];
    $currencyCode = (string)$params['currency'];
    $systemUrl = (string)$params['systemurl'];
    $langPayNow = (string)$params['langpaynow'];
    $moduleName = (string)$params['paymentmethod'];
    $client = is_array($params['clientdetails'] ?? null) ? $params['clientdetails'] : [];

    $logger = new PnPSs2Logger(
        __DIR__ . '/plugnpayss2/logs',
        !empty($params['debugLog'])
    );

    $fields = PnPSs2Protocol::buildHostedFields([
        'gateway_account' => $accountId,
        'amount' => $amount,
        'currency' => $currencyCode,
        'invoice_id' => $invoiceId,
        'success_url' => PnPSs2Protocol::callbackUrl($systemUrl, $moduleName),
        'billing_company' => (string)($client['companyname'] ?? ''),
        'billing_name' => trim((string)($client['firstname'] ?? '') . ' ' . (string)($client['lastname'] ?? '')),
        'billing_address_1' => (string)($client['address1'] ?? ''),
        'billing_address_2' => (string)($client['address2'] ?? ''),
        'billing_city' => (string)($client['city'] ?? ''),
        'billing_state' => (string)($client['state'] ?? ''),
        'billing_postal_code' => (string)($client['postcode'] ?? ''),
        'billing_country' => (string)($client['country'] ?? ''),
        'billing_phone' => (string)($client['phonenumber'] ?? ''),
        'billing_email' => (string)($client['email'] ?? ''),
        'ip_address' => (string)($_SERVER['REMOTE_ADDR'] ?? ''),
    ]);

    $logger->log('Submit-Data', $fields);

    $htmlOutput = '<form method="post" action="' . htmlspecialchars(PnPSs2Protocol::ENDPOINT, ENT_QUOTES, 'UTF-8') . '">';
    foreach ($fields as $name => $value) {
        $htmlOutput .= '<input type="hidden" name="'
            . htmlspecialchars((string)$name, ENT_QUOTES, 'UTF-8')
            . '" value="'
            . htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8')
            . '" />';
    }
    $htmlOutput .= '<input type="submit" value="' . htmlspecialchars($langPayNow, ENT_QUOTES, 'UTF-8') . '" />';
    $htmlOutput .= '</form>';

    return $htmlOutput;
}
