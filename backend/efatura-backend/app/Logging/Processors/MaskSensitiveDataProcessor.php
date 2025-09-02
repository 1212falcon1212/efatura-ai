<?php

namespace App\Logging\Processors;

class MaskSensitiveDataProcessor
{
    private array $sensitiveKeys = [
        'password', 'current_password', 'new_password', 'token', 'api_key',
        'authorization', 'auth', 'secret', 'client_secret',
        'tckn_vkn', 'vkn', 'tckn', 'email', 'electronicmail', 'phone', 'telephone',
    ];

    public function __invoke(array $record): array
    {
        if (isset($record['context'])) {
            $record['context'] = $this->maskArray($record['context']);
        }
        if (isset($record['extra'])) {
            $record['extra'] = $this->maskArray($record['extra']);
        }
        if (is_string($record['message'] ?? null)) {
            $record['message'] = $this->maskString($record['message']);
        }
        return $record;
    }

    private function maskArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->maskArray($value);
                continue;
            }
            if (is_string($value)) {
                // Anahtar bazlı maskeleme
                if ($this->isSensitiveKey((string) $key)) {
                    $data[$key] = $this->maskValue($value);
                    continue;
                }
                // Değer bazlı maskeleme (regex)
                $data[$key] = $this->maskString($value);
            }
        }
        return $data;
    }

    private function isSensitiveKey(string $key): bool
    {
        $key = strtolower($key);
        foreach ($this->sensitiveKeys as $sensitive) {
            if (str_contains($key, $sensitive)) {
                return true;
            }
        }
        return false;
    }

    private function maskValue(string $value): string
    {
        $len = strlen($value);
        if ($len <= 4) { return str_repeat('*', $len); }
        return substr($value, 0, 2) . str_repeat('*', max(0, $len - 4)) . substr($value, -2);
    }

    private function maskString(string $text): string
    {
        // E-posta maskeleme
        $text = preg_replace_callback('/([A-Z0-9._%+-]+)@([A-Z0-9.-]+\.[A-Z]{2,})/i', function ($m) {
            $name = $m[1];
            $domain = $m[2];
            $masked = strlen($name) <= 2 ? str_repeat('*', strlen($name)) : substr($name, 0, 1) . str_repeat('*', strlen($name) - 2) . substr($name, -1);
            return $masked . '@' . $domain;
        }, $text);

        // 11 haneli TCKN gibi sayıları maskeleme
        $text = preg_replace('/\b(\d{3})(\d{5})(\d{3})\b/', '$1*****$3', $text);

        return $text;
    }
}


