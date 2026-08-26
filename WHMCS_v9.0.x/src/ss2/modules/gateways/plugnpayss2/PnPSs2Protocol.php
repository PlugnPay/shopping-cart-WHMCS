<?php
/**
 * Pure Smart Screens v2 protocol helpers (no WHMCS dependencies).
 *
 * @copyright Copyright (c) PlugnPay Technologies
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0
 */

declare(strict_types=1);

class PnPSs2Protocol
{
    public const ENDPOINT = 'https://pay1.plugnpay.com/pay/';
    public const CLIENT_IDENTIFIER = 'WHMCS_SS2';
    public const CUSTOM_INVOICE = 'whmcsinvoice';
    public const CUSTOM_AMOUNT = 'whmcsamount';
    public const VERSION = '1.0.0';

    /**
     * @param array<string, mixed> $response
     */
    public static function extractCustomValue(array $response, string $name): string
    {
        $name = strtolower(trim($name));
        for ($i = 1; $i <= 10; $i++) {
            $nameKey = 'pt_custom_name_' . $i;
            $valueKey = 'pt_custom_value_' . $i;
            if (!isset($response[$nameKey])) {
                continue;
            }
            if (strtolower(trim((string)$response[$nameKey])) === $name) {
                return isset($response[$valueKey]) ? (string)$response[$valueKey] : '';
            }
        }

        if (isset($response[$name])) {
            return (string)$response[$name];
        }

        return '';
    }

    /**
     * @param array<string, mixed> $response
     */
    public static function isSuccessfulResponse(array $response): bool
    {
        $status = strtolower(trim((string)($response['pi_response_status'] ?? '')));
        return $status === 'success';
    }

    /**
     * @param mixed $returnedAmount
     * @param mixed $expectedAmount
     */
    public static function amountsMatch($returnedAmount, $expectedAmount): bool
    {
        $a = number_format((float)$returnedAmount, 2, '.', '');
        $b = number_format((float)$expectedAmount, 2, '.', '');
        return $a === $b;
    }

    /**
     * @param array<string, mixed> $response
     */
    public static function gatewayAccountMatches(array $response, string $expected): bool
    {
        $returned = trim((string)($response['pt_gateway_account'] ?? ''));
        if ($returned === '') {
            return true;
        }

        return strcasecmp($returned, trim($expected)) === 0;
    }

    /**
     * @param array<string, mixed> $response
     */
    public static function callbackInvoiceId(array $response): string
    {
        $fromCustom = self::extractCustomValue($response, self::CUSTOM_INVOICE);
        if ($fromCustom !== '') {
            return $fromCustom;
        }

        return trim((string)($response['pt_account_code_1'] ?? ''));
    }

    /**
     * @param array<string, mixed> $response
     */
    public static function callbackTransactionId(array $response): string
    {
        return trim((string)($response['pt_order_id'] ?? ''));
    }

    /**
     * @param array<string, mixed> $response
     */
    public static function callbackAmount(array $response): string
    {
        return trim((string)($response['pt_transaction_amount'] ?? ''));
    }

    /**
     * @param array<string, mixed> $response
     */
    public static function callbackFee(array $response): string
    {
        $fee = trim((string)($response['pt_convenience_fee_amount'] ?? ''));
        return $fee === '' ? '0' : $fee;
    }

    public static function callbackUrl(string $systemUrl, string $moduleName): string
    {
        return rtrim($systemUrl, '/') . '/modules/gateways/callback/' . $moduleName . '.php';
    }

    /**
     * @param mixed $invoiceId
     */
    public static function invoiceViewUrl(string $systemUrl, $invoiceId): string
    {
        return rtrim($systemUrl, '/') . '/viewinvoice.php?id=' . rawurlencode((string)$invoiceId);
    }

    /**
     * Build hosted-fields map for POST to https://pay1.plugnpay.com/pay/
     *
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public static function buildHostedFields(array $input): array
    {
        $invoiceId = (string)$input['invoice_id'];
        $amount = (string)$input['amount'];
        $currency = (string)$input['currency'];

        return [
            'pt_gateway_account' => trim((string)$input['gateway_account']),
            'pt_transaction_amount' => $amount,
            'pt_currency' => $currency,
            'pt_currency_code' => $currency,
            'pb_post_auth' => 'no',
            'pt_account_code_1' => $invoiceId,
            'pt_billing_company' => (string)($input['billing_company'] ?? ''),
            'pt_payment_name' => (string)($input['billing_name'] ?? ''),
            'pt_billing_address_1' => (string)($input['billing_address_1'] ?? ''),
            'pt_billing_address_2' => (string)($input['billing_address_2'] ?? ''),
            'pt_billing_city' => (string)($input['billing_city'] ?? ''),
            'pt_billing_state' => (string)($input['billing_state'] ?? ''),
            'pt_billing_postal_code' => (string)($input['billing_postal_code'] ?? ''),
            'pt_billing_country' => (string)($input['billing_country'] ?? ''),
            'pt_billing_phone_number' => (string)($input['billing_phone'] ?? ''),
            'pt_billing_email_address' => (string)($input['billing_email'] ?? ''),
            'pt_client_identifier' => self::CLIENT_IDENTIFIER,
            'pt_ip_address' => (string)($input['ip_address'] ?? ''),
            'pb_transition_type' => 'post',
            'pb_success_url' => (string)$input['success_url'],
            'pd_collect_shipping_information' => 'no',
            'pd_display_items' => 'no',
            'pt_custom_name_1' => self::CUSTOM_INVOICE,
            'pt_custom_value_1' => $invoiceId,
            'pt_custom_name_2' => self::CUSTOM_AMOUNT,
            'pt_custom_value_2' => $amount,
        ];
    }
}
