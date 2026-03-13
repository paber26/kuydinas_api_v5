<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TryoutRegistration;
use App\Models\TryoutResult;
use Illuminate\Http\Request;

class TryoutResultController extends Controller
{
    /**
     * Get result of a tryout for logged in user
     */
    public function show(Request $request, $tryoutId)
    {
        $user = $request->user();

        $result = TryoutResult::where('user_id', $user->id)
            ->where('tryout_id', $tryoutId)
            ->first();

        if (!$result) {
            return response()->json([
                'status' => false,
                'message' => 'Hasil tryout tidak ditemukan'
            ], 404);
        }

        $registration = TryoutRegistration::where('user_id', $user->id)
            ->where('tryout_id', $tryoutId)
            ->first();

        return response()->json([
            'status' => true,
            'data' => [
                'score' => (int) ($result->score ?? 0),
                'correct_answer' => (int) ($result->correct_answer ?? 0),
                'answers' => $result->answers ?? [],
                'finished_at' => optional($registration?->finished_at)->toDateTimeString(),
            ]
        ]);
    }

    /**
     * History of tryouts for the logged-in user
     */
    public function history(Request $request)
    {
        $user = $request->user();

        $results = TryoutResult::where('user_id', $user->id)
            ->latest()
            ->get();

        return response()->json([
            'status' => true,
            'data' => $results
        ]);
    }
}
