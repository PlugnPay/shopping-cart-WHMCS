<?php
/**
 * PlugnPay Remote API client (pnpremote.cgi).
 *
 * Pure helpers plus cURL transport. No WHMCS dependencies.
 *
 * @copyright Copyright (c) PlugnPay Technologies
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0
 * @see https://docs.plugnpay.com/docs/integration-specifications-documents/remote-api-integration-specification/
 */

declare(strict_types=1);

class PnPApi
{
    public const ENDPOINT = 'https://pay1.plugnpay.com/payment/pnpremote.cgi';
    public const CLIENT_IDENTIFIER = 'WHMCS_API';
    public const VERSION = '1.0.2';

    private string $publisherName;
    private string $publisherPassword;
    private ?PnPLogger $logger;
    private string $lastRawResponse = '';
    private int $commErrNo = 0;
    private string $commError = '';
    /** @var array<string, mixed> */
    private array $commInfo = [];

    public function __construct(string $publisherName, string $publisherPassword, ?PnPLogger $logger = null)
    {
        $this->publisherName = self::filterAccount($publisherName);
        $this->publisherPassword = self::filterPassword($publisherPassword);
        $this->logger = $logger;
    }

    public function getLastRawResponse(): string
    {
        return $this->lastRawResponse;
    }

    public function getCommError(): string
    {
        return $this->commError;
    }

    public function getCommErrNo(): int
    {
        return $this->commErrNo;
    }

    /**
     * @return array<string, mixed>
     */
    public function getCommInfo(): array
    {
        return $this->commInfo;
    }

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

    public static function filterPassword(mixed $value): string
    {
        $s = str_replace("\0", '', (string)$value);
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $s) ?? '';

        return self::clip(trim($s), 128);
    }

    public static function filterCardNumber(mixed $value): string
    {
        $s = self::filterDigits($value, 19);
        $len = strlen($s);
        if ($len < 13) {
            return '';
        }

        return $s;
    }

    public static function filterCvv(mixed $value): string
    {
        $s = self::filterDigits($value, 4);
        $len = strlen($s);
        if ($len < 3) {
            return '';
        }

        return $s;
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
     * WHMCS supplies expiry as MMYY; Remote API expects MM/YY.
     */
    public static function formatCardExp(string $exp): string
    {
        $digits = substr(preg_replace('/\D/', '', $exp) ?? '', 0, 4);
        if (strlen($digits) !== 4) {
            return '';
        }

        $month = (int)substr($digits, 0, 2);
        if ($month < 1 || $month > 12) {
            return '';
        }

        return substr($digits, 0, 2) . '/' . substr($digits, 2, 2);
    }

    /**
     * Authorization / sale. Expects card and order fields already prepared.
     * Caller should set authtype to authonly or authpostauth.
     *
     * @param array<string, string|int|float> $fields
     * @return array<string, string>
     */
    public function authorize(array $fields): array
    {
        $payload = array_merge([
            'mode' => 'auth',
            'paymethod' => 'credit',
            'client' => self::CLIENT_IDENTIFIER,
        ], $fields);

        return $this->request($payload);
    }

    /**
     * @param array<string, string> $response
     */
    public function isApproved(array $response): bool
    {
        $final = strtolower((string)($response['FinalStatus'] ?? ''));
        $success = strtolower((string)($response['success'] ?? ''));

        return $final === 'success' || $success === 'yes';
    }

    /**
     * @param array<string, string> $response
     */
    public static function transactionId(array $response): string
    {
        $orderId = preg_replace('/[^A-Za-z0-9._-]/', '', (string)($response['orderID'] ?? $response['orderid'] ?? '')) ?? '';

        return substr($orderId, 0, 64);
    }

    /**
     * @param array<string, string> $response
     */
    public static function declineReason(array $response): string
    {
        $msg = self::filterString((string)($response['MErrMsg'] ?? $response['auth-msg'] ?? ''), 255);
        if ($msg !== '') {
            return $msg;
        }

        $status = self::filterString((string)($response['FinalStatus'] ?? ''), 64);
        return $status !== '' ? $status : 'Declined';
    }

    /**
     * Build hyphenated Remote API fields.
     *
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public static function buildAuthorizeFields(array $input): array
    {
        $amount = self::filterAmount($input['amount'] ?? 0);
        $authtype = ((string)($input['authtype'] ?? 'authonly') === 'authpostauth')
            ? 'authpostauth'
            : 'authonly';

        $orderUid = self::filterInvoiceId($input['invoice_id'] ?? $input['order_uid'] ?? '');

        $fields = [
            'mode' => 'auth',
            'paymethod' => 'credit',
            'authtype' => $authtype,
            'easycart' => '1',
            'shipinfo' => '1',
            'orderID' => $orderUid,
            'acct_code' => $orderUid,
            'card-amount' => $amount,
            'card-number' => self::filterCardNumber($input['card_number'] ?? ''),
            'card-exp' => self::formatCardExp((string)($input['card_exp'] ?? '')),
            'card-name' => self::filterString($input['card_name'] ?? '', 80),
            'card-company' => self::filterString($input['card_company'] ?? '', 80),
            'card-address1' => self::filterString($input['card_address1'] ?? '', 128),
            'card-address2' => self::filterString($input['card_address2'] ?? '', 128),
            'card-city' => self::filterString($input['card_city'] ?? '', 64),
            'card-state' => self::filterString($input['card_state'] ?? '', 64),
            'card-zip' => self::filterString($input['card_zip'] ?? '', 16),
            'card-country' => self::filterCountry($input['card_country'] ?? ''),
            'phone' => self::filterPhone($input['phone'] ?? ''),
            'email' => self::filterEmail($input['email'] ?? ''),
            'shipname' => self::filterString($input['ship_name'] ?? '', 80),
            'address1' => self::filterString($input['ship_address1'] ?? '', 128),
            'address2' => self::filterString($input['ship_address2'] ?? '', 128),
            'city' => self::filterString($input['ship_city'] ?? '', 64),
            'state' => self::filterString($input['ship_state'] ?? '', 64),
            'zip' => self::filterString($input['ship_zip'] ?? '', 16),
            'country' => self::filterCountry($input['ship_country'] ?? ''),
            'shipping' => self::filterAmount($input['shipping'] ?? '0.00'),
            'tax' => self::filterAmount($input['tax'] ?? '0.00'),
            'currency' => self::filterCurrency($input['currency'] ?? ''),
            'ipaddress' => self::filterIp($input['ip_address'] ?? ''),
            'dontsndmail' => 'yes',
        ];

        $cvv = self::filterCvv($input['card_cvv'] ?? '');
        if ($cvv !== '') {
            $fields['card-cvv'] = $cvv;
        }

        $publisherEmail = self::filterEmail($input['publisher_email'] ?? '');
        if ($publisherEmail !== '') {
            $fields['publisher-email'] = $publisherEmail;
            $fields['notify-email'] = $publisherEmail;
        }

        if ((float)$amount <= 0) {
            $fields['mode'] = 'checkcard';
            unset($fields['card-amount']);
        }

        if (!empty($input['items']) && is_array($input['items'])) {
            $j = 1;
            foreach ($input['items'] as $product) {
                if (!is_array($product)) {
                    continue;
                }
                $fields['item' . $j] = self::filterString($product['sku'] ?? $product['model'] ?? '', 32);
                $fields['cost' . $j] = self::filterAmount($product['price'] ?? $product['cost'] ?? 0);
                $qty = max(1, (int)self::filterDigits($product['quantity'] ?? $product['qty'] ?? 1, 6));
                $fields['quantity' . $j] = (string)$qty;
                $fields['description' . $j] = self::filterString(strip_tags((string)($product['name'] ?? '')), 255);
                $j++;
            }
        }

        return $fields;
    }

    /**
     * @return array<string, string>
     */
    public static function parseResponse(string $raw): array
    {
        $parsed = [];
        parse_str($raw, $parsed);

        $out = [];
        foreach ($parsed as $key => $value) {
            $keyStr = self::filterString((string)$key, 64);
            if ($keyStr === '') {
                continue;
            }
            if (is_array($value)) {
                $out[$keyStr] = self::filterString(implode(',', $value), 512);
            } else {
                $out[$keyStr] = self::filterString((string)$value, 512);
            }
        }

        return $out;
    }

    /**
     * @param array<string, string|int|float> $fields
     * @return array<string, string>
     */
    public function request(array $fields): array
    {
        if (!function_exists('curl_init')) {
            $this->commErrNo = -1;
            $this->commError = 'PHP cURL extension is not available';
            $this->lastRawResponse = '';
            if ($this->logger) {
                $this->logger->log('cURL missing', ['error' => $this->commError]);
            }
            return [
                'FinalStatus' => 'problem',
                'success' => 'no',
                'MErrMsg' => $this->commError,
            ];
        }

        $payload = array_merge([
            'publisher-name' => $this->publisherName,
            'publisher-password' => $this->publisherPassword,
        ], $fields);

        foreach ($payload as $key => $value) {
            if ($value === null || $value === '') {
                unset($payload[$key]);
            }
        }

        $body = http_build_query($payload);

        if ($this->logger) {
            $this->logger->log('Request to PlugnPay', [
                'endpoint' => self::ENDPOINT,
                'fields' => $payload,
            ]);
        }

        $ch = curl_init(self::ENDPOINT);
        $opts = [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ];
        if (defined('CURLPROTO_HTTPS')) {
            $opts[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
            $opts[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTPS;
        }
        curl_setopt_array($ch, $opts);

        $raw = curl_exec($ch);
        $this->commErrNo = (int)curl_errno($ch);
        $this->commError = (string)curl_error($ch);
        $this->commInfo = curl_getinfo($ch) ?: [];
        curl_close($ch);

        unset($payload['publisher-password'], $payload['card-number'], $payload['card-cvv'], $body);

        $this->lastRawResponse = is_string($raw) ? $raw : '';

        if ($this->lastRawResponse === '' || $this->commErrNo !== 0) {
            $response = [
                'FinalStatus' => 'problem',
                'success' => 'no',
                'MErrMsg' => $this->commError !== ''
                    ? $this->commError
                    : 'Empty response from PlugnPay (check cURL connectivity / firewall)',
            ];
            if ($this->logger) {
                $this->logger->log('Communication failure', [
                    'errno' => $this->commErrNo,
                    'error' => $this->commError,
                    'info' => $this->commInfo,
                ]);
            }
            return $response;
        }

        $response = self::parseResponse($this->lastRawResponse);

        if ($this->logger) {
            $this->logger->log('Response from PlugnPay', [
                'FinalStatus' => $response['FinalStatus'] ?? '',
                'success' => $response['success'] ?? '',
                'orderID' => $response['orderID'] ?? ($response['orderid'] ?? ''),
                'auth-code' => $response['auth-code'] ?? ($response['auth_code'] ?? ''),
                'resp-code' => $response['resp-code'] ?? ($response['resp_code'] ?? ''),
                'MErrMsg' => $response['MErrMsg'] ?? '',
                'avs-code' => $response['avs-code'] ?? ($response['avs_code'] ?? ''),
                'cvvresp' => $response['cvvresp'] ?? '',
            ]);
        }

        return $response;
    }
}
