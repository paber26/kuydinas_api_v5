<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TryoutRegistration;
use App\Models\TryoutResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class TryoutResultController extends Controller
{
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

        $score = (int) ($result->score ?? 0);
        $rank = TryoutResult::where('tryout_id', $tryoutId)
            ->where('score', '>', $score)
            ->count() + 1;

        return response()->json([
            'status' => true,
            'data' => [
                'score' => $score,
                'rank' => $rank,
                'correct_answer' => (int) ($result->correct_answer ?? 0),
                'answers' => $result->answers ?? [],
                'session_state' => $this->sessionStateForResponse($result),
                'started_at' => optional($result->started_at)->toDateTimeString(),
                'finished_at' => optional($registration?->finished_at)->toDateTimeString(),
            ]
        ]);
    }

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

    private function sessionStateForResponse(?TryoutResult $result): array
    {
        if (!Schema::hasColumn('tryout_results', 'session_state')) {
            return [
                'current_index' => 0,
                'current_question_id' => null,
                'flagged_question_ids' => [],
                'visited_question_ids' => [],
                'last_interaction' => null,
            ];
        }

        return $result?->session_state ?? [
            'current_index' => 0,
            'current_question_id' => null,
            'flagged_question_ids' => [],
            'visited_question_ids' => [],
            'last_interaction' => null,
        ];
    }
}
