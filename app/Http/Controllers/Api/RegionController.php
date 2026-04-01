<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;

class RegionController extends Controller
{
    private const DEFAULT_TIMEOUT_SECONDS = 15;

    public function provinces(): JsonResponse
    {
        return $this->proxyWilayah('provinces.json');
    }

    public function regencies(string $provinceCode): JsonResponse
    {
        return $this->proxyWilayah(sprintf('regencies/%s.json', $provinceCode));
    }

    public function districts(string $regencyCode): JsonResponse
    {
        return $this->proxyWilayah(sprintf('districts/%s.json', $regencyCode));
    }

    private function proxyWilayah(string $path): JsonResponse
    {
        $baseUrl = rtrim((string) config('services.wilayah.base_url', 'https://wilayah.id/api'), '/');

        try {
            $response = Http::timeout(self::DEFAULT_TIMEOUT_SECONDS)
                ->acceptJson()
                ->get(sprintf('%s/%s', $baseUrl, ltrim($path, '/')));

            if ($response->failed()) {
                return response()->json([
                    'status' => false,
                    'message' => 'Gagal memuat data wilayah dari layanan eksternal.',
                ], $response->status());
            }

            $data = $response->json();

            return response()->json([
                'status' => true,
                'data' => is_array($data) ? $data : [],
            ]);
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => false,
                'message' => 'Layanan wilayah sedang tidak dapat diakses.',
            ], 502);
        }
    }
}
