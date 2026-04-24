<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TryoutRegistration;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class PublicStatsController extends Controller
{
    /**
     * GET /api/public/stats
     *
     * Mengembalikan statistik agregat platform untuk social proof di blog.
     * Tidak mengandung data PII (nama, email, atau identifier user individual).
     */
    public function index()
    {
        $data = Cache::remember('public_stats', 600, function () {
            return [
                'total_registrations' => TryoutRegistration::count(),
                'total_completed'     => TryoutRegistration::where('status', 'completed')->count(),
                'total_users'         => User::count(),
                'cached_at'           => Carbon::now()->toIso8601String(),
            ];
        });

        return response()->json([
            'status' => true,
            'data'   => [
                'total_registrations' => $data['total_registrations'],
                'total_completed'     => $data['total_completed'],
                'total_users'         => $data['total_users'],
            ],
            'meta'   => [
                'cached_at' => $data['cached_at'],
            ],
        ]);
    }
}
