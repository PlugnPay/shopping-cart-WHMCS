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
    public const VERSION = '1.0.2';

    public static function clip(string $value, int $maxLen): string
    {
        if ($maxLen < 1) {
            return '';
        }
        if (function_exists('mb_substr') && function_exists('mb_strlen')) {
            return mb_strlen($value) > $maxLen ? (string)mb_substr($value, 0, $maxLen) : $value;
        }

        return strlen($value) > $maxLen ? substr($value, 0, $maxLen) : $value;
    }

    /**
     * Strip NULs and other control characters, then clip.
     */
    public static function filterString(mixed $value, int $maxLen = 255): string
    {
        $s = trim((string)$value);
        $s = str_replace("\0", '', $s);
        $s = preg_replace('/[\x00-\x1F\x7F]/', '', $s);
        $s = is_string($s) ? $s : '';

        return self::clip($s, $maxLen);
    }

    public static function filterDigits(mixed $value, int $maxLen = 32): string
    {
        $s = preg_replace('/\D/', '', (string)$value) ?? '';

        return substr($s, 0, $maxLen);
    }

    public static function filterAmount(mixed $value): string
    {
        if (is_string($value)) {
            $value = str_replace([',', ' '], '', $value);
        }
        $n = (float)$value;
        if (!is_finite($n) || $n < 0) {
            $n = 0.0;
        }

        return number_format($n, 2, '.', '');
    }

    public static function filterCurrency(mixed $value): string
    {
        $s = strtoupper(preg_replace('/[^A-Za-z]/', '', (string)$value) ?? '');

        return substr($s, 0, 3);
    }

    public static function filterEmail(mixed $value): string
    {
        $s = self::filterString($value, 254);
        if ($s === '' || filter_var($s, FILTER_VALIDATE_EMAIL) === false) {
            return '';
        }

        return $s;
    }

    public static function filterIp(mixed $value): string
    {
        $s = trim((string)$value);
        if ($s === '' || filter_var($s, FILTER_VALIDATE_IP) === false) {
            return '';
        }

        return $s;
    }

    public static function filterInvoiceId(mixed $value): string
    {
        return self::filterDigits($value, 20);
    }

    public static function filterAccount(mixed $value): string
    {
        $s = preg_replace('/[^A-Za-z0-9._@-]/', '', (string)$value) ?? '';

        return substr($s, 0, 50);
    }

    public static function filterTransId(mixed $value): string
    {
        $s = preg_replace('/[^A-Za-z0-9._-]/', '', (string)$value) ?? '';

        return substr($s, 0, 64);
    }

    public static function filterCountry(mixed $value): string
    {
        $s = strtoupper(preg_replace('/[^A-Za-z]/', '', (string)$value) ?? '');

        return substr($s, 0, 2);
    }

    public static function filterPhone(mixed $value): string
    {
        $s = preg_replace('/[^0-9+\-(). ]/', '', (string)$value) ?? '';

        return substr(trim($s), 0, 32);
    }

    public static function filterHttpsUrl(mixed $value): string
    {
        $s = self::filterString($value, 2048);
        if ($s === '' || stripos($s, 'https://') !== 0) {
            return '';
        }

        return $s;
    }

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
        return self::filterAmount($returnedAmount) === self::filterAmount($expectedAmount);
    }

    /**
     * @param array<string, mixed> $response
     */
    public static function gatewayAccountMatches(array $response, string $expected): bool
    {
        $returned = self::filterAccount($response['pt_gateway_account'] ?? '');
        if ($returned === '') {
            return true;
        }

        return strcasecmp($returned, self::filterAccount($expected)) === 0;
    }

    /**
     * @param array<string, mixed> $response
     */
    public static function callbackInvoiceId(array $response): string
    {
        $fromCustom = self::extractCustomValue($response, self::CUSTOM_INVOICE);
        if ($fromCustom !== '') {
            return self::filterInvoiceId($fromCustom);
        }

        return self::filterInvoiceId($response['pt_account_code_1'] ?? '');
    }

    /**
     * @param array<string, mixed> $response
     */
    public static function callbackTransactionId(array $response): string
    {
        return self::filterTransId($response['pt_order_id'] ?? '');
    }

    /**
     * @param array<string, mixed> $response
     */
    public static function callbackAmount(array $response): string
    {
        $raw = trim((string)($response['pt_transaction_amount'] ?? ''));
        if ($raw === '') {
            return '';
        }

        return self::filterAmount($raw);
    }

    /**
     * @param array<string, mixed> $response
     */
    public static function callbackFee(array $response): string
    {
        $fee = trim((string)($response['pt_convenience_fee_amount'] ?? ''));
        return $fee === '' ? '0' : self::filterAmount($fee);
    }

    public static function callbackUrl(string $systemUrl, string $moduleName): string
    {
        $base = self::filterHttpsUrl(rtrim($systemUrl, '/') . '/');
        $mod = preg_replace('/[^A-Za-z0-9_-]/', '', $moduleName) ?? '';
        if ($base === '' || $mod === '') {
            return '';
        }

        return rtrim($base, '/') . '/modules/gateways/callback/' . $mod . '.php';
    }

    /**
     * @param mixed $invoiceId
     */
    public static function invoiceViewUrl(string $systemUrl, $invoiceId): string
    {
        $base = rtrim(self::filterString($systemUrl, 2048), '/');
        $id = self::filterInvoiceId($invoiceId);
        if ($base === '') {
            return '';
        }

        return $base . '/viewinvoice.php?id=' . rawurlencode($id !== '' ? $id : '0');
    }

    /**
     * Remaining invoice balance from WHMCS ledger columns.
     *
     * @param mixed $total
     * @param mixed $credit
     * @param mixed $amountIn
     * @param mixed $amountOut
     */
    public static function invoiceAmountDue($total, $credit = 0, $amountIn = 0, $amountOut = 0): string
    {
        $due = (float)$total - (float)$credit - (float)$amountIn + (float)$amountOut;
        if (!is_finite($due) || $due < 0) {
            $due = 0.0;
        }

        return number_format($due, 2, '.', '');
    }

    /**
     * Build hosted-fields map for POST to https://pay1.plugnpay.com/pay/
     *
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public static function buildHostedFields(array $input): array
    {
        $invoiceId = self::filterInvoiceId($input['invoice_id'] ?? '');
        $amount = self::filterAmount($input['amount'] ?? 0);
        $currency = self::filterCurrency($input['currency'] ?? '');

        return [
            'pt_gateway_account' => self::filterAccount($input['gateway_account'] ?? ''),
            'pt_transaction_amount' => $amount,
            'pt_currency' => $currency,
            'pt_currency_code' => $currency,
            'pb_post_auth' => 'no',
            'pt_account_code_1' => $invoiceId,
            'pt_billing_company' => self::filterString($input['billing_company'] ?? '', 80),
            'pt_payment_name' => self::filterString($input['billing_name'] ?? '', 80),
            'pt_billing_address_1' => self::filterString($input['billing_address_1'] ?? '', 128),
            'pt_billing_address_2' => self::filterString($input['billing_address_2'] ?? '', 128),
            'pt_billing_city' => self::filterString($input['billing_city'] ?? '', 64),
            'pt_billing_state' => self::filterString($input['billing_state'] ?? '', 64),
            'pt_billing_postal_code' => self::filterString($input['billing_postal_code'] ?? '', 16),
            'pt_billing_country' => self::filterCountry($input['billing_country'] ?? ''),
            'pt_billing_phone_number' => self::filterPhone($input['billing_phone'] ?? ''),
            'pt_billing_email_address' => self::filterEmail($input['billing_email'] ?? ''),
            'pt_client_identifier' => self::CLIENT_IDENTIFIER,
            'pt_ip_address' => self::filterIp($input['ip_address'] ?? ''),
            'pb_transition_type' => 'post',
            'pb_success_url' => self::filterHttpsUrl($input['success_url'] ?? ''),
            'pd_collect_shipping_information' => 'no',
            'pd_display_items' => 'no',
            'pt_custom_name_1' => self::CUSTOM_INVOICE,
            'pt_custom_value_1' => $invoiceId,
            'pt_custom_name_2' => self::CUSTOM_AMOUNT,
            'pt_custom_value_2' => $amount,
        ];
    }
}
