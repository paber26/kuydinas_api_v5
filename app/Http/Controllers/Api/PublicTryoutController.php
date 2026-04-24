<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tryout;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class PublicTryoutController extends Controller
{
    /**
     * Hitung status_label berdasarkan type, free_start_date, dan free_valid_until.
     *
     * @param  Tryout  $tryout
     * @return string  "upcoming" | "active" | "ended"
     */
    private function computeStatusLabel(Tryout $tryout): string
    {
        $now = Carbon::now();

        if ($tryout->type === 'free') {
            // Jika free_start_date ada dan sekarang sebelum tanggal mulai → upcoming
            if ($tryout->free_start_date !== null && $now->lt(Carbon::parse($tryout->free_start_date))) {
                return 'upcoming';
            }

            // Jika free_valid_until ada dan sekarang setelah akhir hari berakhir → ended
            if ($tryout->free_valid_until !== null && $now->gt(Carbon::parse($tryout->free_valid_until)->endOfDay())) {
                return 'ended';
            }

            return 'active';
        }

        // Premium / regular: selalu aktif jika sudah publish
        return 'active';
    }

    /**
     * GET /api/public/tryouts
     *
     * Mengembalikan daftar tryout yang sudah dipublish dan aktif/mendatang
     * untuk konsumsi publik tanpa autentikasi.
     */
    public function index()
    {
        $cachedAt = Carbon::now()->toIso8601String();

        $data = Cache::remember('public_tryouts', 300, function () use (&$cachedAt) {
            $cachedAt = Carbon::now()->toIso8601String();

            $tryouts = Tryout::where('status', 'publish')
                ->withCount('registrations')
                ->orderBy('free_start_date', 'asc')
                ->orderBy('created_at', 'desc')
                ->get();

            $now = Carbon::now();
            $cutoff = $now->copy()->subDays(7);

            $result = [];

            foreach ($tryouts as $tryout) {
                $label = $this->computeStatusLabel($tryout);

                // Sertakan "ended" hanya jika berakhir dalam 7 hari terakhir
                if ($label === 'ended') {
                    if ($tryout->free_valid_until === null || Carbon::parse($tryout->free_valid_until)->lt($cutoff)) {
                        continue;
                    }
                }

                $result[] = [
                    'id'                  => $tryout->id,
                    'title'               => $tryout->title,
                    'type'                => $tryout->type,
                    'duration'            => $tryout->duration,
                    'free_start_date'     => $tryout->free_start_date
                        ? Carbon::parse($tryout->free_start_date)->toDateString()
                        : null,
                    'free_valid_until'    => $tryout->free_valid_until
                        ? Carbon::parse($tryout->free_valid_until)->toDateString()
                        : null,
                    'status_label'        => $label,
                    'quota'               => $tryout->quota,
                    'registrations_count' => $tryout->registrations_count,
                ];
            }

            return [
                'items'     => $result,
                'cached_at' => $cachedAt,
            ];
        });

        return response()->json([
            'status' => true,
            'data'   => $data['items'],
            'meta'   => [
                'cached_at' => $data['cached_at'],
                'total'     => count($data['items']),
            ],
        ]);
    }
}
