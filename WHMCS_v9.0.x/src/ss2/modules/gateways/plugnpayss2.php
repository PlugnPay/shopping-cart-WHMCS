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
    $accountId = PnPSs2Protocol::filterAccount($params['accountId'] ?? '');
    $invoiceId = PnPSs2Protocol::filterInvoiceId($params['invoiceid'] ?? '');
    $amount = PnPSs2Protocol::filterAmount($params['amount'] ?? 0);
    $currencyCode = PnPSs2Protocol::filterCurrency($params['currency'] ?? '');
    $systemUrl = (string)($params['systemurl'] ?? '');
    $langPayNow = PnPSs2Protocol::filterString($params['langpaynow'] ?? 'Pay Now', 80);
    $moduleName = preg_replace('/[^A-Za-z0-9_-]/', '', (string)($params['paymentmethod'] ?? '')) ?? '';
    $client = is_array($params['clientdetails'] ?? null) ? $params['clientdetails'] : [];

    $logger = new PnPSs2Logger(
        __DIR__ . '/plugnpayss2/logs',
        !empty($params['debugLog'])
    );

    if ($accountId === '') {
        return '<p>PlugnPay Smart Screens v2 is not configured. Please contact support.</p>';
    }

    $successUrl = PnPSs2Protocol::callbackUrl($systemUrl, $moduleName !== '' ? $moduleName : 'plugnpayss2');
    if ($successUrl === '') {
        return '<p>Storefront HTTPS is required for Smart Screens v2 checkout.</p>';
    }

    $fields = PnPSs2Protocol::buildHostedFields([
        'gateway_account' => $accountId,
        'amount' => $amount,
        'currency' => $currencyCode,
        'invoice_id' => $invoiceId,
        'success_url' => $successUrl,
        'billing_company' => $client['companyname'] ?? '',
        'billing_name' => trim(
            PnPSs2Protocol::filterString($client['firstname'] ?? '', 40)
            . ' '
            . PnPSs2Protocol::filterString($client['lastname'] ?? '', 40)
        ),
        'billing_address_1' => $client['address1'] ?? '',
        'billing_address_2' => $client['address2'] ?? '',
        'billing_city' => $client['city'] ?? '',
        'billing_state' => $client['state'] ?? '',
        'billing_postal_code' => $client['postcode'] ?? '',
        'billing_country' => $client['country'] ?? '',
        'billing_phone' => $client['phonenumber'] ?? '',
        'billing_email' => $client['email'] ?? '',
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
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
