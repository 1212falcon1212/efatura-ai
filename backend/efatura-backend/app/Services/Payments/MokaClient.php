<?php

namespace App\Services\Payments;

use Illuminate\Support\Facades\Http;

class MokaClient
{
    public function __construct(
        private readonly string $baseUrl = '',
        private readonly string $dealerCode = '',
        private readonly string $username = '',
        private readonly string $password = '',
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            baseUrl: config('payments.moka.base_url'),
            dealerCode: config('payments.moka.dealer_code'),
            username: config('payments.moka.username'),
            password: config('payments.moka.password'),
        );
    }

    public function charge(array $paymentDealerRequest): array
    {
        $endpoint = rtrim($this->baseUrl, '/').'/PaymentDealer/DoDirectPayment';
        $checkKey = hash('sha256', $this->dealerCode.'MK'.$this->username.'PD'.$this->password);
        $body = [
            'PaymentDealerAuthentication' => [
                'DealerCode' => $this->dealerCode,
                'Username' => $this->username,
                'Password' => $this->password,
                'CheckKey' => $checkKey,
            ],
            'PaymentDealerRequest' => $paymentDealerRequest,
        ];
        $res = Http::withOptions(['version' => '2.0']) // TLS 1.2+
            ->asJson()
            ->post($endpoint, $body);

        $json = $res->json() ?? [];
        $ok = false;
        $message = null;
        if (is_array($json)) {
            $resultCode = $json['ResultCode'] ?? null;
            $message = $json['ResultMessage'] ?? null;
            $ok = ($resultCode === 'Success') && !empty($json['Data']);
        }

        return [
            'status' => $ok,
            'http' => $res->status(),
            'json' => $json,
            'message' => $message,
        ];
    }

    public function chargeThreeD(array $paymentDealerRequest): array
    {
        $endpoint = rtrim($this->baseUrl, '/').'/PaymentDealer/DoDirectPaymentThreeD';
        $checkKey = hash('sha256', $this->dealerCode.'MK'.$this->username.'PD'.$this->password);
        $body = [
            'PaymentDealerAuthentication' => [
                'DealerCode' => $this->dealerCode,
                'Username' => $this->username,
                'Password' => $this->password,
                'CheckKey' => $checkKey,
            ],
            'PaymentDealerRequest' => $paymentDealerRequest,
        ];
        $res = Http::withOptions(['version' => '2.0'])
            ->asJson()
            ->post($endpoint, $body);

        $json = $res->json() ?? [];
        $ok = false;
        $message = null;
        if (is_array($json)) {
            $resultCode = $json['ResultCode'] ?? null;
            $message = $json['ResultMessage'] ?? null;
            // ThreeD başlangıcı için Success bekleriz ve Data dolu olmalı (ör. Url/CodeForHash)
            $ok = ($resultCode === 'Success') && !empty($json['Data']);
        }
        return [
            'status' => $ok,
            'http' => $res->status(),
            'json' => $json,
            'message' => $message,
        ];
    }
}
