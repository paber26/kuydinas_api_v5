<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class MidtransSnapService
{
    public function createTransaction(array $payload): array
    {
        $response = Http::withBasicAuth(config('midtrans.server_key'), '')
            ->acceptJson()
            ->post($this->baseUrl() . '/snap/v1/transactions', $payload)
            ->throw();

        return $response->json();
    }

    public function verifySignature(string $orderId, string $statusCode, string $grossAmount, string $signatureKey): bool
    {
        $expectedSignature = hash(
            'sha512',
            $orderId . $statusCode . $grossAmount . config('midtrans.server_key')
        );

        return hash_equals($expectedSignature, $signatureKey);
    }

    public function getTransactionStatus(string $orderId): array
    {
        $response = Http::withBasicAuth(config('midtrans.server_key'), '')
            ->acceptJson()
            ->get($this->baseUrlStatus() . '/v2/' . $orderId . '/status')
            ->throw();

        return $response->json();
    }

    private function baseUrl(): string
    {
        return config('midtrans.is_production')
            ? 'https://app.midtrans.com'
            : 'https://app.sandbox.midtrans.com';
    }

    private function baseUrlStatus(): string
    {
        return config('midtrans.is_production')
            ? 'https://api.midtrans.com'
            : 'https://api.sandbox.midtrans.com';
    }
}
