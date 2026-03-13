<?php

namespace App\Http\Controllers\Api\User;

use App\Http\Controllers\Controller;
use App\Models\Tryout;

class TryoutController extends Controller
{
    /* ===============================
       LIST TRYOUT PUBLISHED
    ================================= */

    public function index()
    {
        $tryouts = Tryout::where('status', 'publish')
            ->withCount('soals')
            ->withCount('registrations')
            ->latest()
            ->get()
            ->map(function ($tryout) {

                $questionCount =
                    ($tryout->twk_target ?? 0) +
                    ($tryout->tiu_target ?? 0) +
                    ($tryout->tkp_target ?? 0);

                return [
                    'id' => $tryout->id,
                    'title' => $tryout->title,

                    // fields tambahan untuk frontend
                    'subtitle' => 'Simulasi lengkap seperti ujian asli',
                    'category' => 'SKD CPNS',

                    'price' => $tryout->price ?? 0,
                    'discount' => $tryout->discount,

                    'isFree' => $tryout->type === 'free',

                    'duration' => $tryout->duration,
                    'questionCount' => $questionCount,

                    'level' => 'Menengah',
                    'seatsLeft' => $tryout->quota
                        ? max($tryout->quota - $tryout->registrations_count, 0)
                        : null,

                    'highlight' => $tryout->type === 'free',
                    'tag' => $tryout->type,

                    // data asli tetap dikirim
                    'twk_target' => $tryout->twk_target,
                    'tiu_target' => $tryout->tiu_target,
                    'tkp_target' => $tryout->tkp_target,
                ];
            });

        return response()->json([
            'status' => true,
            'data' => $tryouts
        ]);
    }

    /* ===============================
       DETAIL TRYOUT
    ================================= */

    public function show($id)
    {
        $tryout = Tryout::where('status','publish')
            ->with('soals')
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $tryout
        ]);
    }
}
