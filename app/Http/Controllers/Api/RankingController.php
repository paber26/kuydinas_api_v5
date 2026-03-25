<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TryoutResult;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class RankingController extends Controller
{
    public function index($tryoutId)
    {
        $ranking = Cache::remember("ranking_tryout_{$tryoutId}", 10, function () use ($tryoutId) {
            return TryoutResult::with('user:id,name')
                ->where('tryout_id', $tryoutId)
                ->orderByDesc('score')
                ->limit(100)
                ->get();
        });

        return response()->json([
            'status' => true,
            'data' => $ranking
        ]);
    }

    public function myRank(Request $request, $tryoutId)
    {
        $userId = $request->user()->id;

        $score = TryoutResult::where('user_id', $userId)
            ->where('tryout_id', $tryoutId)
            ->value('score');

        if ($score === null) {
            return response()->json([
                'status' => false,
                'message' => 'User belum mengerjakan tryout'
            ]);
        }

        $rank = TryoutResult::where('tryout_id', $tryoutId)
            ->where('score', '>', $score)
            ->count() + 1;

        return response()->json([
            'status' => true,
            'rank' => $rank
        ]);
    }
}
