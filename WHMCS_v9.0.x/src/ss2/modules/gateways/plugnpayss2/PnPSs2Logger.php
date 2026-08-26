<?php
/**
 * PlugnPay Smart Screens v2 debug logger with PCI-oriented secret redaction.
 *
 * @copyright Copyright (c) PlugnPay Technologies
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL-3.0
 */

declare(strict_types=1);

class PnPSs2Logger
{
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
            @mkdir($this->logDir, 0750, true);
        }

        $line = date('Y-m-d H:i:s') . ' ' . $message;
        if ($context !== []) {
            $line .= "\n" . print_r($this->sanitize($context), true);
        }
        $line .= "\n----------------------------------------\n";

        $file = $this->logDir . '/plugnpay_ss2_' . date('Ymd') . '.log';
        @file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
        @chmod($file, 0640);
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
            if ($this->isPanKey($keyStr)) {
                $out[$key] = $this->maskPanValue($value);
                continue;
            }
            if ($this->isSecretKey($keyStr)) {
                $out[$key] = '***REDACTED***';
                continue;
            }
            if (is_array($value)) {
                $out[$key] = $this->sanitize($value);
            } elseif (is_string($value)) {
                $out[$key] = $this->maskPanInString($value);
            } else {
                $out[$key] = $value;
            }
        }
        return $out;
    }

    private function isPanKey(string $key): bool
    {
        $n = $this->normalizeKey($key);
        if ($n === '') {
            return false;
        }

        foreach (['card-number', 'cardnum', 'cc-number', 'ccnumber', 'cc-num'] as $needle) {
            if ($n === $needle || str_ends_with($n, '-' . $needle) || str_contains($n, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function isSecretKey(string $key): bool
    {
        $n = $this->normalizeKey($key);
        if ($n === '' || in_array($n, ['cvvresp', 'cvv-resp', 'avs-code', 'avs-resp'], true)) {
            return false;
        }

        if (str_contains($n, 'password')) {
            return true;
        }

        foreach (['track1', 'track2', 'track3', 'magstripe', 'magnetic-stripe'] as $needle) {
            if ($n === $needle || str_contains($n, $needle)) {
                return true;
            }
        }

        if (
            (str_contains($n, 'cvv') && !str_contains($n, 'cvvresp') && !str_contains($n, 'cvv-resp'))
            || str_contains($n, 'cvc')
            || preg_match('/(^|-)(cid|cav2)(-|$)/', $n) === 1
        ) {
            return true;
        }

        if (preg_match('/(^|-)pin(block)?(-|$)/', $n) === 1) {
            return true;
        }

        return false;
    }

    private function normalizeKey(string $key): string
    {
        return strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $key) ?? '');
    }

    private function maskPanValue(mixed $value): string
    {
        if (is_array($value)) {
            return '***REDACTED***';
        }
        $digits = preg_replace('/\D/', '', (string)$value) ?? '';
        if (strlen($digits) < 4) {
            return '***REDACTED***';
        }

        return str_repeat('X', strlen($digits) - 4) . substr($digits, -4);
    }

    private function maskPanInString(string $value): string
    {
        $masked = preg_replace_callback(
            '/(?<!\d)(?:\d[ \-]?){13,19}(?!\d)/',
            function (array $m): string {
                $digits = preg_replace('/\D/', '', $m[0]) ?? '';
                $len = strlen($digits);
                if ($len < 13 || $len > 19 || !$this->passesLuhn($digits)) {
                    return $m[0];
                }

                return str_repeat('X', $len - 4) . substr($digits, -4);
            },
            $value
        );

        return is_string($masked) ? $masked : $value;
    }

    private function passesLuhn(string $digits): bool
    {
        $sum = 0;
        $alt = false;
        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $n = (int)$digits[$i];
            if ($alt) {
                $n *= 2;
                if ($n > 9) {
                    $n -= 9;
                }
            }
            $sum += $n;
            $alt = !$alt;
        }

        return $sum % 10 === 0;
    }
}
