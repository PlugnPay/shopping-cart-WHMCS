<?php
/**
 * PlugnPay Remote API debug logger with secret redaction.
 *
 * @copyright Copyright (c) PlugnPay Technologies
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0
 */

declare(strict_types=1);

class PnPLogger
{
    /** @var list<string> */
    private const SENSITIVE_KEYS = [
        'card-number',
        'card_number',
        'card-cvv',
        'card_cvv',
        'publisher-password',
        'publisher_password',
        'cc_number',
        'cc_cvv',
        'cardnum',
        'cccvv',
    ];

    private string $logDir;
    private bool $enabled;

    public function __construct(string $logDir, bool $enabled = false)
    {
        $this->logDir = rtrim($logDir, '/\\');
        $this->enabled = $enabled;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function log(string $message, array $context = []): void
    {
        if (!$this->enabled) {
            return;
        }

        if ($this->logDir !== '' && !is_dir($this->logDir)) {
            @mkdir($this->logDir, 0755, true);
        }

        $line = date('Y-m-d H:i:s') . ' ' . $message;
        if ($context !== []) {
            $line .= "\n" . print_r($this->sanitize($context), true);
        }
        $line .= "\n----------------------------------------\n";

        $file = $this->logDir . '/plugnpay_api_' . date('Ymd') . '.log';
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function sanitize(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            $keyStr = (string)$key;
            if ($this->isSensitiveKey($keyStr)) {
                if (stripos($keyStr, 'number') !== false && is_string($value) && strlen($value) >= 4) {
                    $out[$key] = str_repeat('X', max(0, strlen($value) - 4)) . substr($value, -4);
                } elseif ($keyStr === 'cardnum' && is_string($value) && strlen($value) >= 4) {
                    $out[$key] = str_repeat('X', max(0, strlen($value) - 4)) . substr($value, -4);
                } else {
                    $out[$key] = '***REDACTED***';
                }
                continue;
            }
            if (is_array($value)) {
                $out[$key] = $this->sanitize($value);
            } else {
                $out[$key] = $value;
            }
        }
        return $out;
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = strtolower(str_replace('_', '-', $key));
        foreach (self::SENSITIVE_KEYS as $sensitive) {
            if ($normalized === strtolower(str_replace('_', '-', $sensitive))) {
                return true;
            }
        }
        return false;
    }
}
