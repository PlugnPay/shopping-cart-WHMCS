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
    public const VERSION = '1.0.0';

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
        $this->publisherName = $publisherName;
        $this->publisherPassword = $publisherPassword;
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

    /**
     * WHMCS supplies expiry as MMYY; Remote API expects MM/YY.
     */
    public static function formatCardExp(string $exp): string
    {
        $digits = preg_replace('/\D/', '', $exp) ?? '';
        if (strlen($digits) === 4) {
            return substr($digits, 0, 2) . '/' . substr($digits, 2, 2);
        }

        return trim($exp);
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
        $orderId = trim((string)($response['orderID'] ?? $response['orderid'] ?? ''));
        return $orderId;
    }

    /**
     * @param array<string, string> $response
     */
    public static function declineReason(array $response): string
    {
        $msg = trim((string)($response['MErrMsg'] ?? $response['auth-msg'] ?? ''));
        if ($msg !== '') {
            return $msg;
        }

        $status = trim((string)($response['FinalStatus'] ?? ''));
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
        $amount = (string)$input['amount'];
        $authtype = ((string)($input['authtype'] ?? 'authonly') === 'authpostauth')
            ? 'authpostauth'
            : 'authonly';

        $orderUid = (string)($input['invoice_id'] ?? $input['order_uid'] ?? '');

        $fields = [
            'mode' => 'auth',
            'paymethod' => 'credit',
            'authtype' => $authtype,
            'easycart' => '1',
            'shipinfo' => '1',
            'orderID' => $orderUid,
            'acct_code' => $orderUid,
            'card-amount' => $amount,
            'card-number' => preg_replace('/\D/', '', (string)($input['card_number'] ?? '')) ?? '',
            'card-exp' => self::formatCardExp((string)($input['card_exp'] ?? '')),
            'card-name' => (string)($input['card_name'] ?? ''),
            'card-company' => (string)($input['card_company'] ?? ''),
            'card-address1' => (string)($input['card_address1'] ?? ''),
            'card-address2' => (string)($input['card_address2'] ?? ''),
            'card-city' => (string)($input['card_city'] ?? ''),
            'card-state' => (string)($input['card_state'] ?? ''),
            'card-zip' => (string)($input['card_zip'] ?? ''),
            'card-country' => (string)($input['card_country'] ?? ''),
            'phone' => (string)($input['phone'] ?? ''),
            'email' => (string)($input['email'] ?? ''),
            'shipname' => (string)($input['ship_name'] ?? ''),
            'address1' => (string)($input['ship_address1'] ?? ''),
            'address2' => (string)($input['ship_address2'] ?? ''),
            'city' => (string)($input['ship_city'] ?? ''),
            'state' => (string)($input['ship_state'] ?? ''),
            'zip' => (string)($input['ship_zip'] ?? ''),
            'country' => (string)($input['ship_country'] ?? ''),
            'shipping' => (string)($input['shipping'] ?? '0.00'),
            'tax' => (string)($input['tax'] ?? '0.00'),
            'currency' => (string)($input['currency'] ?? ''),
            'ipaddress' => (string)($input['ip_address'] ?? ''),
            'dontsndmail' => 'yes',
        ];

        $cvv = trim((string)($input['card_cvv'] ?? ''));
        if ($cvv !== '') {
            $fields['card-cvv'] = $cvv;
        }

        $publisherEmail = trim((string)($input['publisher_email'] ?? ''));
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
                $fields['item' . $j] = (string)($product['sku'] ?? $product['model'] ?? '');
                $fields['cost' . $j] = number_format((float)($product['price'] ?? $product['cost'] ?? 0), 2, '.', '');
                $fields['quantity' . $j] = (string)($product['quantity'] ?? $product['qty'] ?? 1);
                $fields['description' . $j] = substr(strip_tags((string)($product['name'] ?? '')), 0, 255);
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
            if (is_array($value)) {
                $out[(string)$key] = implode(',', $value);
            } else {
                $out[(string)$key] = (string)$value;
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
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ]);

        $raw = curl_exec($ch);
        $this->commErrNo = (int)curl_errno($ch);
        $this->commError = (string)curl_error($ch);
        $this->commInfo = curl_getinfo($ch) ?: [];
        curl_close($ch);

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
